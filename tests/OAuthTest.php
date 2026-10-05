<?php

namespace FLAIRUK\Uber\Tests;

use FLAIRUK\Uber\Exceptions\AuthenticationException;
use FLAIRUK\Uber\Exceptions\ConfigurationException;
use FLAIRUK\Uber\Facades\Uber;
use FLAIRUK\Uber\OAuth\AccessToken;
use FLAIRUK\Uber\OAuth\OAuth;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;

class OAuthTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('uber.oauth.redirect_uri', 'https://app.test/uber/callback');
    }

    #[Test]
    public function the_authorize_url_carries_scopes_state_and_pkce(): void
    {
        $url = Uber::oauth()->authorizeUrl(['profile', 'request'], 'state-1', codeChallenge: 'challenge', extra: ['prompt' => 'login']);

        $this->assertSame(
            'https://auth.uber.com/oauth/v2/authorize?client_id=test-client&response_type=code&redirect_uri=https%3A%2F%2Fapp.test%2Fuber%2Fcallback&scope=profile%20request&state=state-1&code_challenge=challenge&code_challenge_method=S256&prompt=login',
            $url,
        );
    }

    #[Test]
    public function codes_are_exchanged_for_tokens(): void
    {
        Http::preventStrayRequests();
        Http::fake([self::TOKEN_URL => Http::response([
            'access_token' => 'user-access',
            'refresh_token' => 'user-refresh',
            'expires_in' => 2592000,
            'scope' => 'profile request',
            'token_type' => 'Bearer',
        ])]);

        $token = Uber::oauth()->exchangeCode('code-1', codeVerifier: 'verifier');

        $this->assertSame('user-access', $token->accessToken);
        $this->assertSame('user-refresh', $token->refreshToken);
        $this->assertSame(['profile', 'request'], $token->scopes);
        $this->assertTrue($token->hasScope('request'));
        $this->assertFalse($token->isExpired());
        Http::assertSent(fn (Request $r) => $r->isForm()
            && $r['grant_type'] === 'authorization_code'
            && $r['code'] === 'code-1'
            && $r['code_verifier'] === 'verifier'
            && $r['redirect_uri'] === 'https://app.test/uber/callback'
            && $r['client_secret'] === 'test-secret');
    }

    #[Test]
    public function tokens_refresh_and_round_trip_through_storage(): void
    {
        Http::preventStrayRequests();
        Http::fake([self::TOKEN_URL => Http::response(['access_token' => 'new', 'refresh_token' => 'r2', 'expires_in' => 60])]);

        $stored = (new AccessToken('old', 'r1', new \DateTimeImmutable('-1 minute')))->toArray();
        $token = AccessToken::fromArray($stored);

        $this->assertTrue($token->isExpired());

        $fresh = Uber::oauth()->refresh($token);

        $this->assertSame('new', $fresh->accessToken);
        $this->assertTrue($fresh->isExpired(leeway: 120));
        Http::assertSent(fn (Request $r) => $r['grant_type'] === 'refresh_token' && $r['refresh_token'] === 'r1');

        $this->expectException(ConfigurationException::class);
        Uber::oauth()->refresh(new AccessToken('no-refresh'));
    }

    #[Test]
    public function bad_codes_raise_an_authentication_exception(): void
    {
        Http::preventStrayRequests();
        Http::fake([self::TOKEN_URL => Http::response(['error' => 'invalid_grant'], 400)]);

        try {
            Uber::oauth()->exchangeCode('used-code');
            $this->fail('Expected exception');
        } catch (AuthenticationException $e) {
            $this->assertSame('invalid_grant', $e->errorCode);
        }
    }

    #[Test]
    public function tokens_can_be_revoked(): void
    {
        Http::preventStrayRequests();
        Http::fake(['https://auth.uber.com/oauth/v2/revoke' => Http::response(['message' => 'OK'])]);

        Uber::oauth()->revoke(new AccessToken('user-access'));

        Http::assertSent(fn (Request $r) => $r['token'] === 'user-access' && $r['client_id'] === 'test-client');
    }

    #[Test]
    public function pushed_authorization_requests_return_a_request_uri(): void
    {
        Http::preventStrayRequests();
        Http::fake(['https://auth.uber.com/oauth/v2/par' => Http::response(['request_uri' => 'urn:ietf:params:oauth:request_uri:abc', 'expires_in' => 300])]);

        $par = Uber::oauth()->pushAuthorizationRequest(['profile'], 'st', loginHint: ['email' => 'jane@example.com']);

        $this->assertSame('urn:ietf:params:oauth:request_uri:abc', $par['request_uri']);
        $this->assertSame(
            'https://auth.uber.com/oauth/v2/authorize?client_id=test-client&request_uri=urn%3Aietf%3Aparams%3Aoauth%3Arequest_uri%3Aabc',
            Uber::oauth()->authorizeUrlFor($par['request_uri']),
        );
        Http::assertSent(fn (Request $r) => $r['login_hint'] === base64_encode('{"email":"jane@example.com"}') && $r['scope'] === 'profile');
    }

    #[Test]
    public function certs_are_cached(): void
    {
        Http::preventStrayRequests();
        Http::fake(['https://auth.uber.com/oauth/v2/certs' => Http::response(['keys' => [['kid' => 'k1', 'kty' => 'RSA']]])]);

        $this->assertSame('k1', Uber::oauth()->certs()[0]['kid']);
        Uber::oauth()->certs();
        Uber::oauth()->certs(fresh: true);

        Http::assertSentCount(2);
    }

    #[Test]
    public function pkce_challenges_match_rfc_7636(): void
    {
        // RFC 7636 appendix B
        $this->assertSame('E9Melhoa2OwvFrEMTJguCHaoeK1t8URWbuGJSstw-cM', OAuth::challenge('dBjftJeZ4CVP-mB92K27uhbUJU1p1r_wW1gFWFOEjXk'));

        $pair = OAuth::pkce();
        $this->assertSame(OAuth::challenge($pair['verifier']), $pair['challenge']);
        $this->assertMatchesRegularExpression('/^[A-Za-z0-9_-]{43,128}$/', $pair['verifier']);
    }

    #[Test]
    public function the_oauth_host_can_be_overridden(): void
    {
        $this->rebuild(['oauth.url' => 'https://login.uber.com']);

        $this->assertStringStartsWith('https://login.uber.com/oauth/v2/authorize?', Uber::oauth()->authorizeUrl(['ads.campaigns.read']));
    }
}
