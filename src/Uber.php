<?php

namespace FLAIRUK\Uber;

use DateTimeInterface;
use FLAIRUK\Uber\Exceptions\AuthenticationException;
use FLAIRUK\Uber\Exceptions\ConfigurationException;
use FLAIRUK\Uber\Exceptions\ConflictException;
use FLAIRUK\Uber\Exceptions\NotFoundException;
use FLAIRUK\Uber\Exceptions\RateLimitException;
use FLAIRUK\Uber\Exceptions\UberException;
use FLAIRUK\Uber\Exceptions\ValidationException;
use FLAIRUK\Uber\OAuth\AccessToken;
use FLAIRUK\Uber\OAuth\OAuth;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory as Http;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response as HttpResponse;

/**
 * Uber API client.
 *
 * Requests are authenticated with the user token from withToken() when there
 * is one (rides for a rider, a store owner's provisioning token), and otherwise
 * with an app token for the scopes the endpoint needs, fetched with the client
 * credentials grant and cached.
 *
 * @see https://developer.uber.com/docs
 */
class Uber
{
    protected ?AccessToken $userToken = null;

    /**
     * @param  array<string, mixed>  $config
     */
    public function __construct(
        protected Http $http,
        protected OAuth $oauth,
        protected array $config,
    ) {}

    // ---------------------------------------------------------------------
    // APIs
    // ---------------------------------------------------------------------

    /**
     * Riders API: products, estimates and ride requests, as a rider (needs withToken()).
     */
    public function rides(): Resources\Rides\Rides
    {
        return new Resources\Rides\Rides($this);
    }

    /**
     * Guest Rides (Uber for Business / Uber Central): rides for people without an Uber account.
     */
    public function guestRides(): Resources\GuestRides\GuestRides
    {
        return new Resources\GuestRides\GuestRides($this);
    }

    /**
     * Uber Health: HIPAA-compliant rides for patients, the same shape as guestRides().
     */
    public function health(): Resources\Health\Health
    {
        return new Resources\Health\Health($this);
    }

    /**
     * Uber Direct: courier deliveries, for a customer id (UBER_DIRECT_CUSTOMER_ID or forCustomer()).
     */
    public function direct(): Resources\Direct\Direct
    {
        return new Resources\Direct\Direct($this);
    }

    /**
     * Uber Eats Marketplace: stores, menus, orders, reports and promotions.
     */
    public function eats(): Resources\Eats\Eats
    {
        return new Resources\Eats\Eats($this);
    }

    /**
     * Uber for Business: receipts, vouchers, organisations, employees, employee trips and statements.
     */
    public function business(): Resources\Business\Business
    {
        return new Resources\Business\Business($this);
    }

    /**
     * Login with Uber profile, account linking, client registration and organisation administration.
     */
    public function identity(): Resources\Identity\Identity
    {
        return new Resources\Identity\Identity($this);
    }

    /**
     * Drivers API: a consenting driver's profile, payments and trips (needs withToken()).
     */
    public function drivers(): Resources\Drivers\Drivers
    {
        return new Resources\Drivers\Drivers($this);
    }

    /**
     * Uber for Suppliers: vehicles, drivers, terms, financing, shifts and reports for fleets.
     */
    public function vehicleSuppliers(): Resources\VehicleSuppliers\VehicleSuppliers
    {
        return new Resources\VehicleSuppliers\VehicleSuppliers($this);
    }

    /**
     * Uber Ads for an ad account (UBER_ADS_ACCOUNT_ID by default; needs withToken()).
     */
    public function ads(?string $accountId = null): Resources\Ads\Ads
    {
        return new Resources\Ads\Ads($this, $accountId ?? $this->config('ads.account_id'));
    }

    /**
     * Uber AI Solutions: data-labelling batches and machine translation.
     */
    public function aiSolutions(): Resources\AiSolutions\AiSolutions
    {
        return new Resources\AiSolutions\AiSolutions($this);
    }

    /**
     * Uber Pay, for onboarded payment providers: deposits, refunds, charges and payouts.
     */
    public function payments(): Resources\Payments\Payments
    {
        return new Resources\Payments\Payments($this);
    }

    public function oauth(): OAuth
    {
        return $this->oauth;
    }

    // ---------------------------------------------------------------------
    // Scoping
    // ---------------------------------------------------------------------

    /**
     * A copy of the client that acts as a user, with their OAuth access token.
     *
     * @param  string|AccessToken|array<string, mixed>  $token  a token, or a stored AccessToken::toArray()
     */
    public function withToken(string|AccessToken|array $token): static
    {
        $clone = clone $this;
        $clone->userToken = match (true) {
            is_string($token) => new AccessToken($token),
            is_array($token) => AccessToken::fromArray($token),
            default => $token,
        };

        return $clone;
    }

    /**
     * A copy of the client that uses app (client credentials) tokens again.
     */
    public function asApp(): static
    {
        $clone = clone $this;
        $clone->userToken = null;

        return $clone;
    }

    public function token(): ?AccessToken
    {
        return $this->userToken;
    }

    /**
     * A copy of the client pointed at the Uber sandbox (or, with false, production).
     */
    public function sandbox(bool $sandbox = true): static
    {
        return $this->withConfig(['sandbox' => $sandbox, 'base_url' => null]);
    }

    /**
     * A copy of the client using another Uber Direct customer id.
     */
    public function forCustomer(string $customerId): static
    {
        return $this->withConfig(['direct' => ['customer_id' => $customerId] + (array) ($this->config['direct'] ?? [])]);
    }

    /**
     * A copy of the client that asks for responses in another language, e.g. "fr_FR".
     */
    public function locale(string $locale): static
    {
        return $this->withConfig(['locale' => $locale]);
    }

    /**
     * The API host. Production is api.uber.com for every API; the sandboxes differ:
     * sandbox-api.uber.com for rides and guest rides, test-api.uber.com for Uber Eats.
     * Uber Direct has no sandbox host: test deliveries use a test customer on production.
     */
    public function baseUrl(?string $api = null): string
    {
        if (filled($this->config['base_url'] ?? null)) {
            return rtrim($this->config['base_url'], '/');
        }

        if (! ($this->config['sandbox'] ?? false)) {
            return 'https://api.uber.com';
        }

        return match ($api) {
            'eats' => 'https://test-api.uber.com',
            'direct' => 'https://api.uber.com',
            default => 'https://sandbox-api.uber.com',
        };
    }

    /**
     * The OAuth host for an API. Uber Eats sandbox tokens come from sandbox-login.uber.com.
     */
    public function authUrl(?string $api = null): string
    {
        if ($api === 'eats' && ($this->config['sandbox'] ?? false) && blank($this->config['oauth']['url'] ?? null)) {
            return 'https://sandbox-login.uber.com';
        }

        return $this->oauth->authUrl();
    }

    public function isSandbox(): bool
    {
        return (bool) ($this->config['sandbox'] ?? false);
    }

    public function config(string $key, mixed $default = null): mixed
    {
        return data_get($this->config, $key, $default);
    }

    // ---------------------------------------------------------------------
    // Requests
    // ---------------------------------------------------------------------

    /**
     * @param  array<string, mixed>  $query
     * @param  list<string>  $scopes  the app-token scopes the endpoint needs, when there is no user token
     */
    public function get(string $path, array $query = [], array $scopes = []): Response
    {
        return $this->send('GET', $path, ['query' => $query], $scopes);
    }

    /**
     * @param  array<string, mixed>|list<mixed>  $body
     * @param  list<string>  $scopes
     * @param  bool  $retry  whether connection errors and 5xx may be retried; false for anything that books, charges or cancels
     */
    public function post(string $path, array $body = [], array $scopes = [], bool $retry = false): Response
    {
        return $this->send('POST', $path, ['json' => $body], $scopes, $retry);
    }

    public function put(string $path, array $body = [], array $scopes = [], bool $retry = false): Response
    {
        return $this->send('PUT', $path, ['json' => $body], $scopes, $retry);
    }

    public function patch(string $path, array $body = [], array $scopes = [], bool $retry = false): Response
    {
        return $this->send('PATCH', $path, ['json' => $body], $scopes, $retry);
    }

    public function delete(string $path, array $body = [], array $scopes = [], bool $retry = false): Response
    {
        return $this->send('DELETE', $path, $body === [] ? [] : ['json' => $body], $scopes, $retry);
    }

    /**
     * Send a request and wrap the result.
     *
     * @param  array{api?: string, query?: array<string, mixed>, json?: array<mixed>, body?: string, multipart?: array<mixed>, headers?: array<string, string|null>}  $options
     * @param  list<string>  $scopes
     * @param  bool|null  $retry  null retries reads (GET) only
     *
     * @throws UberException
     */
    public function send(string $method, string $path, array $options = [], array $scopes = [], ?bool $retry = null): Response
    {
        $retry ??= $method === 'GET';
        $api = $options['api'] ?? null;
        $url = str_starts_with($path, 'https://') ? $path : $this->baseUrl($api).'/'.ltrim($path, '/');

        $options = array_filter([
            'query' => isset($options['query']) ? $this->normalise($options['query'], forQuery: true) : null,
            'json' => isset($options['json']) ? $this->normalise($options['json']) : null,
            'body' => $options['body'] ?? null,
            'multipart' => $options['multipart'] ?? null,
            'headers' => array_filter($options['headers'] ?? [], fn ($value) => $value !== null && $value !== ''),
        ], fn ($value) => $value !== null && $value !== []);

        // Uber's write endpoints expect a JSON object even when there is nothing to send.
        if (! isset($options['json']) && ! isset($options['body']) && ! isset($options['multipart']) && in_array($method, ['POST', 'PUT', 'PATCH'], true)) {
            $options['json'] = new \stdClass;
        }

        $authUrl = $this->authUrl($api);
        $response = $this->attempt($method, $url, $options, $scopes, $retry, $authUrl);

        // A cached app token Uber no longer accepts (revoked, rotated secret): fetch a new one, once.
        if ($response->status() === 401 && $this->userToken === null && $scopes !== []) {
            $this->oauth->forgetClientToken($scopes, $authUrl);
            $response = $this->attempt($method, $url, $options, $scopes, $retry, $authUrl);
        }

        $this->throwIfFailed($response);

        $body = $response->json();

        return new Response(
            is_array($body) ? $body : [],
            $response->status(),
            $response->headers(),
        );
    }

    /**
     * An authenticated request builder, for anything this client doesn't wrap.
     *
     * @param  list<string>  $scopes
     *
     * @throws ConfigurationException
     */
    public function request(array $scopes = [], ?string $authUrl = null): PendingRequest
    {
        return $this->http->acceptJson()
            ->timeout((int) ($this->config['timeout'] ?? 30))
            ->withToken($this->bearer($scopes, $authUrl))
            ->withHeaders(array_filter(['Accept-Language' => $this->config['locale'] ?? null]));
    }

    /**
     * @param  list<string>  $scopes
     */
    protected function bearer(array $scopes, ?string $authUrl = null): string
    {
        if ($this->userToken !== null) {
            return $this->userToken->accessToken;
        }

        if ($scopes === []) {
            throw new ConfigurationException('This Uber endpoint acts for a user: pass their access token with Uber::withToken().');
        }

        return $this->oauth->clientToken($scopes, $authUrl)->accessToken;
    }

    /**
     * @param  array<string, mixed>  $options
     * @param  list<string>  $scopes
     */
    protected function attempt(string $method, string $url, array $options, array $scopes, bool $retry, ?string $authUrl = null): HttpResponse
    {
        $request = $this->request($scopes, $authUrl);

        if ($retry) {
            [$times, $sleep] = $this->config['retry'] ?? [2, 500];

            $request = $request->retry($times, $sleep, fn (\Throwable $e) => $e instanceof ConnectionException
                || ($e instanceof RequestException && $e->response->serverError()), throw: false);
        }

        if (isset($options['headers'])) {
            $request = $request->withHeaders($options['headers']);
            unset($options['headers']);
        }

        if (isset($options['body'])) {
            $request = $request->withBody($options['body'], $request->getOptions()['headers']['Content-Type'] ?? 'application/json');
            unset($options['body']);
        }

        if (isset($options['multipart'])) {
            $request = $request->asMultipart();
            $options['multipart'] = $this->multipart($options['multipart']);
        }

        try {
            return $request->send($method, $url, $options);
        } catch (ConnectionException $e) {
            throw new UberException('Could not reach Uber: '.$e->getMessage(), previous: $e);
        }
    }

    protected function throwIfFailed(HttpResponse $response): void
    {
        if ($response->successful()) {
            return;
        }

        throw match (true) {
            $response->status() === 429 => RateLimitException::fromResponse($response),
            in_array($response->status(), [401, 403], true) => AuthenticationException::fromResponse($response),
            $response->status() === 404 => NotFoundException::fromResponse($response),
            $response->status() === 409 => ConflictException::fromResponse($response),
            in_array($response->status(), [400, 422], true) => ValidationException::fromResponse($response),
            default => UberException::fromResponse($response),
        };
    }

    /**
     * Drop nulls, format dates as RFC 3339, and booleans as "true"/"false" in query strings.
     *
     * @param  array<array-key, mixed>  $data
     * @return array<array-key, mixed>
     */
    protected function normalise(array $data, bool $forQuery = false): array
    {
        $normalised = [];

        foreach ($data as $key => $value) {
            if ($value === null) {
                continue;
            }

            $normalised[$key] = match (true) {
                $value instanceof DateTimeInterface => $value->format(DATE_RFC3339),
                $value instanceof \BackedEnum => $value->value,
                is_array($value) => $this->normalise($value, $forQuery),
                $forQuery && is_bool($value) => $value ? 'true' : 'false',
                default => $value,
            };
        }

        return $normalised;
    }

    /**
     * Turn ['name' => contents] or Guzzle-style parts into multipart parts.
     *
     * @param  array<array-key, mixed>  $parts
     * @return list<array<string, mixed>>
     */
    protected function multipart(array $parts): array
    {
        $multipart = [];

        foreach ($parts as $name => $part) {
            $multipart[] = is_array($part) && isset($part['name'])
                ? $part
                : ['name' => $name, 'contents' => is_array($part) ? json_encode($part) : $part];
        }

        return $multipart;
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    protected function withConfig(array $overrides): static
    {
        $clone = clone $this;
        $clone->config = array_replace($this->config, $overrides);

        return $clone;
    }
}
