<?php

namespace FLAIRUK\Uber\OAuth;

use FLAIRUK\Uber\Exceptions\AuthenticationException;
use FLAIRUK\Uber\Exceptions\ConfigurationException;
use FLAIRUK\Uber\Exceptions\UberException;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory as Http;
use Illuminate\Http\Client\Response as HttpResponse;

/**
 * OAuth 2.0 against auth.uber.com.
 *
 * - Authorization code (with optional PKCE) for acting as a rider, a restaurant
 *   owner provisioning a store, or a business admin: authorizeUrl(), exchangeCode(), refresh().
 * - Client credentials for app-level scopes (eats.*, eats.deliveries, guests.trips…):
 *   clientToken(), cached until shortly before it expires.
 *
 * @see https://developer.uber.com/docs/riders/guides/authentication/introduction
 */
class OAuth
{
    /**
     * @param  array<string, mixed>  $config  the "uber" config array
     */
    public function __construct(
        protected Http $http,
        protected ?Cache $cache,
        protected array $config,
    ) {}

    /**
     * The URL to send the user to, to authorize your app.
     *
     * @param  list<string>  $scopes  e.g. ['profile', 'request']; defaults to config('uber.oauth.scopes')
     * @param  string  $state  a random CSRF token; store it and compare it on the callback
     * @param  string|null  $codeChallenge  for PKCE, the challenge from pkce()
     * @param  array<string, string>  $extra  further query parameters, e.g. ['prompt' => 'consent']
     */
    public function authorizeUrl(
        array $scopes = [],
        ?string $state = null,
        ?string $redirectUri = null,
        ?string $codeChallenge = null,
        array $extra = [],
    ): string {
        $query = array_filter([
            'client_id' => $this->clientId(),
            'response_type' => 'code',
            'redirect_uri' => $redirectUri ?? $this->config['oauth']['redirect_uri'] ?? null,
            'scope' => implode(' ', $scopes ?: (array) ($this->config['oauth']['scopes'] ?? [])),
            'state' => $state,
            'code_challenge' => $codeChallenge,
            'code_challenge_method' => $codeChallenge === null ? null : 'S256',
        ] + $extra, fn ($value) => $value !== null && $value !== '');

        return $this->authUrl().'/oauth/v2/authorize?'.http_build_query($query, '', '&', PHP_QUERY_RFC3986);
    }

    /**
     * Exchange the code from the callback for an access and refresh token.
     *
     * Pass $codeVerifier if you sent a code challenge.
     *
     * @throws AuthenticationException
     */
    public function exchangeCode(string $code, ?string $redirectUri = null, ?string $codeVerifier = null): AccessToken
    {
        return $this->token(array_filter([
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => $redirectUri ?? $this->config['oauth']['redirect_uri'] ?? null,
            'code_verifier' => $codeVerifier,
        ], fn ($value) => $value !== null));
    }

    /**
     * A fresh access token for a user's refresh token. Uber may rotate the
     * refresh token too, so store the whole returned token.
     *
     * @throws AuthenticationException
     */
    public function refresh(string|AccessToken $refreshToken): AccessToken
    {
        if ($refreshToken instanceof AccessToken) {
            $refreshToken = $refreshToken->refreshToken
                ?? throw new ConfigurationException('This access token has no refresh token.');
        }

        return $this->token(['grant_type' => 'refresh_token', 'refresh_token' => $refreshToken]);
    }

    /**
     * Pushed authorization request: send the authorize parameters (and an optional
     * login hint) server-side, then redirect the user to authorizeUrlFor($requestUri).
     * The request_uri lasts five minutes.
     *
     * @param  list<string>  $scopes
     * @param  array{email?: string, phone?: string, first_name?: string, last_name?: string}  $loginHint
     * @return array{request_uri: string, expires_in: int}
     *
     * @throws UberException
     */
    public function pushAuthorizationRequest(
        array $scopes = [],
        ?string $state = null,
        ?string $redirectUri = null,
        ?string $codeChallenge = null,
        array $loginHint = [],
    ): array {
        $response = $this->send('/oauth/v2/par', array_filter([
            'client_id' => $this->clientId(),
            'client_secret' => $this->clientSecret(),
            'response_type' => 'code',
            'redirect_uri' => $redirectUri ?? $this->config['oauth']['redirect_uri'] ?? null,
            'scope' => implode(' ', $scopes ?: (array) ($this->config['oauth']['scopes'] ?? [])),
            'state' => $state,
            'code_challenge' => $codeChallenge,
            'code_challenge_method' => $codeChallenge === null ? null : 'S256',
            'login_hint' => $loginHint === [] ? null : base64_encode((string) json_encode($loginHint)),
        ], fn ($value) => $value !== null && $value !== ''));

        if ($response->failed() || ! is_string($response->json('request_uri'))) {
            throw UberException::fromResponse($response);
        }

        return ['request_uri' => (string) $response->json('request_uri'), 'expires_in' => (int) $response->json('expires_in', 300)];
    }

    /**
     * The authorize URL for a request_uri from pushAuthorizationRequest().
     */
    public function authorizeUrlFor(string $requestUri): string
    {
        return $this->authUrl().'/oauth/v2/authorize?'.http_build_query([
            'client_id' => $this->clientId(),
            'request_uri' => $requestUri,
        ], '', '&', PHP_QUERY_RFC3986);
    }

    /**
     * Uber's ID-token signing keys (JWKS), cached for an hour. Keys rotate: on an
     * unknown "kid", call with $fresh = true.
     *
     * @return list<array<string, string>>
     */
    public function certs(bool $fresh = false): array
    {
        $key = 'uber:certs:'.sha1($this->authUrl());

        if (! $fresh && $this->cache !== null && is_array($cached = $this->cache->get($key))) {
            return $cached;
        }

        try {
            $response = $this->http->acceptJson()->timeout((int) ($this->config['timeout'] ?? 30))->get($this->authUrl().'/oauth/v2/certs');
        } catch (ConnectionException $e) {
            throw new UberException('Could not reach Uber: '.$e->getMessage(), previous: $e);
        }

        if ($response->failed()) {
            throw UberException::fromResponse($response);
        }

        $keys = array_values(array_filter((array) $response->json('keys'), 'is_array'));
        $this->cache?->put($key, $keys, 3600);

        return $keys;
    }

    /**
     * Revoke a user's access or refresh token.
     *
     * @throws UberException
     */
    public function revoke(string|AccessToken $token): void
    {
        $response = $this->send('/oauth/v2/revoke', [
            'client_id' => $this->clientId(),
            'client_secret' => $this->clientSecret(),
            'token' => (string) $token,
        ]);

        if ($response->failed()) {
            throw UberException::fromResponse($response);
        }
    }

    /**
     * An app (client credentials) token for these scopes, from the cache when it can be.
     *
     * @param  list<string>  $scopes  e.g. ['eats.deliveries']
     *
     * @throws AuthenticationException
     */
    public function clientToken(array $scopes, ?string $authUrl = null): AccessToken
    {
        sort($scopes);

        $key = $this->clientTokenKey($scopes, $authUrl);

        if ($this->cache !== null && is_array($cached = $this->cache->get($key))) {
            $token = AccessToken::fromArray($cached);

            if (! $token->isExpired()) {
                return $token;
            }
        }

        $token = $this->token(['grant_type' => 'client_credentials', 'scope' => implode(' ', $scopes)], $authUrl);

        if ($this->cache !== null) {
            $ttl = $token->expiresAt === null ? 3600 : max(1, $token->expiresAt->getTimestamp() - time() - 300);
            $this->cache->put($key, $token->toArray(), $ttl);
        }

        return $token;
    }

    /**
     * Forget a cached app token, e.g. after Uber rejected it.
     *
     * @param  list<string>  $scopes
     */
    public function forgetClientToken(array $scopes, ?string $authUrl = null): void
    {
        $this->cache?->forget($this->clientTokenKey($scopes, $authUrl));
    }

    /**
     * @param  list<string>  $scopes
     */
    protected function clientTokenKey(array $scopes, ?string $authUrl): string
    {
        sort($scopes);

        return 'uber:token:'.sha1($this->clientId().'|'.($authUrl ?? $this->authUrl()).'|'.implode(' ', $scopes));
    }

    /**
     * A PKCE code verifier and its S256 challenge. Keep the verifier (e.g. in the
     * session), send the challenge to authorizeUrl() and the verifier to exchangeCode().
     *
     * @return array{verifier: string, challenge: string}
     */
    public static function pkce(): array
    {
        $verifier = rtrim(strtr(base64_encode(random_bytes(64)), '+/', '-_'), '=');

        return ['verifier' => $verifier, 'challenge' => static::challenge($verifier)];
    }

    public static function challenge(string $verifier): string
    {
        return rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');
    }

    public function authUrl(): string
    {
        return rtrim((string) ($this->config['oauth']['url'] ?? 'https://auth.uber.com'), '/');
    }

    /**
     * @param  array<string, string>  $form
     */
    protected function token(array $form, ?string $authUrl = null): AccessToken
    {
        $response = $this->send('/oauth/v2/token', [
            'client_id' => $this->clientId(),
            'client_secret' => $this->clientSecret(),
        ] + $form, $authUrl);

        if ($response->failed() || ! is_string($response->json('access_token'))) {
            throw AuthenticationException::fromResponse($response);
        }

        return AccessToken::fromArray((array) $response->json());
    }

    /**
     * @param  array<string, string>  $form
     */
    protected function send(string $path, array $form, ?string $authUrl = null): HttpResponse
    {
        try {
            return $this->http->asForm()
                ->acceptJson()
                ->timeout((int) ($this->config['timeout'] ?? 30))
                ->post(rtrim($authUrl ?? $this->authUrl(), '/').$path, $form);
        } catch (ConnectionException $e) {
            throw new UberException('Could not reach Uber: '.$e->getMessage(), previous: $e);
        }
    }

    protected function clientId(): string
    {
        return filled($this->config['client_id'] ?? null)
            ? (string) $this->config['client_id']
            : throw new ConfigurationException('No Uber client ID: set UBER_CLIENT_ID.');
    }

    protected function clientSecret(): string
    {
        return filled($this->config['client_secret'] ?? null)
            ? (string) $this->config['client_secret']
            : throw new ConfigurationException('No Uber client secret: set UBER_CLIENT_SECRET.');
    }
}
