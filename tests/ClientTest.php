<?php

namespace FLAIRUK\Uber\Tests;

use FLAIRUK\Uber\Exceptions\AuthenticationException;
use FLAIRUK\Uber\Exceptions\ConfigurationException;
use FLAIRUK\Uber\Exceptions\ConflictException;
use FLAIRUK\Uber\Exceptions\NotFoundException;
use FLAIRUK\Uber\Exceptions\RateLimitException;
use FLAIRUK\Uber\Exceptions\UberException;
use FLAIRUK\Uber\Exceptions\ValidationException;
use FLAIRUK\Uber\Facades\Uber;
use FLAIRUK\Uber\OAuth\AccessToken;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;

class ClientTest extends TestCase
{
    #[Test]
    public function app_endpoints_fetch_a_client_credentials_token_for_their_scope(): void
    {
        $this->fake([self::BASE.'/v1/customers/cust-1/deliveries/del_1' => Http::response(['id' => 'del_1'])]);

        $this->assertSame('del_1', Uber::direct()->find('del_1')['id']);

        Http::assertSent(fn (Request $r) => $r->url() === self::TOKEN_URL
            && $r['grant_type'] === 'client_credentials'
            && $r['scope'] === 'eats.deliveries'
            && $r['client_id'] === 'test-client'
            && $r['client_secret'] === 'test-secret');
        Http::assertSent(fn (Request $r) => $r->url() === self::BASE.'/v1/customers/cust-1/deliveries/del_1'
            && $r->hasHeader('Authorization', 'Bearer app-token'));
    }

    #[Test]
    public function app_tokens_are_cached_per_scope_set(): void
    {
        $this->fake();

        Uber::direct()->find('del_1');
        Uber::direct()->find('del_2');
        Uber::eats()->stores()->status('s1');

        $tokenRequests = Http::recorded(fn (Request $r) => $r->url() === self::TOKEN_URL);
        $this->assertCount(2, $tokenRequests);
        $this->assertSame(['eats.deliveries', 'eats.store'], $tokenRequests->map(fn ($pair) => $pair[0]['scope'])->values()->all());
    }

    #[Test]
    public function an_expired_cached_token_is_replaced(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            self::TOKEN_URL => Http::sequence()->push(['access_token' => 'short', 'expires_in' => 30])->push(['access_token' => 'fresh', 'expires_in' => 3600]),
            '*' => Http::response([]),
        ]);

        Uber::direct()->find('del_1');
        Uber::direct()->find('del_2');

        $this->assertSame(['Bearer short', 'Bearer fresh'], array_map(fn (Request $r) => $r->header('Authorization')[0], $this->apiRequests()));
    }

    #[Test]
    public function a_rejected_app_token_is_replaced_once(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            self::TOKEN_URL => Http::sequence()->push(['access_token' => 'revoked', 'expires_in' => 3600])->push(['access_token' => 'new', 'expires_in' => 3600]),
            self::BASE.'/*' => Http::sequence()->push(['code' => 'unauthorized'], 401)->push(['id' => 'del_1']),
        ]);

        $this->assertSame('del_1', Uber::direct()->find('del_1')['id']);
        $this->assertSame(['Bearer revoked', 'Bearer new'], array_map(fn (Request $r) => $r->header('Authorization')[0], $this->apiRequests()));
    }

    #[Test]
    public function with_token_acts_as_the_user_without_changing_the_default(): void
    {
        $this->fake();

        Uber::withToken('rider-token')->rides()->me();
        Uber::withToken(new AccessToken('other'))->rides()->me();
        Uber::withToken(['access_token' => 'stored', 'expires_at' => time() + 60])->rides()->me();

        $this->assertSame(['Bearer rider-token', 'Bearer other', 'Bearer stored'], array_map(fn (Request $r) => $r->header('Authorization')[0], $this->apiRequests()));
        Http::assertNotSent(fn (Request $r) => $r->url() === self::TOKEN_URL);
        $this->assertNull(Uber::token());
    }

    #[Test]
    public function user_endpoints_without_a_user_token_fail_before_any_request(): void
    {
        Http::preventStrayRequests();

        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage('withToken');

        Uber::rides()->me();
    }

    #[Test]
    public function missing_credentials_fail_before_any_request(): void
    {
        $this->rebuild(['client_secret' => null]);
        Http::preventStrayRequests();

        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage('UBER_CLIENT_SECRET');

        Uber::direct()->find('del_1');
    }

    #[Test]
    public function refused_token_requests_raise_an_authentication_exception(): void
    {
        Http::preventStrayRequests();
        Http::fake([self::TOKEN_URL => Http::response(['error' => 'invalid_scope', 'error_description' => 'scope not allowed'], 400)]);

        try {
            Uber::direct()->find('del_1');
            $this->fail('Expected exception');
        } catch (AuthenticationException $e) {
            $this->assertSame('invalid_scope', $e->errorCode);
            $this->assertStringContainsString('scope not allowed', $e->getMessage());
        }
    }

    #[Test]
    public function errors_map_to_exceptions_with_uber_codes_and_metadata(): void
    {
        $cases = [
            [400, ['code' => 'invalid_params', 'message' => 'bad address', 'metadata' => ['param_details' => 'dropoff_address']], ValidationException::class],
            [422, ['code' => 'validation_failed', 'message' => 'nope'], ValidationException::class],
            [403, ['code' => 'forbidden', 'message' => 'no'], AuthenticationException::class],
            [404, ['code' => 'delivery_not_found', 'message' => 'gone'], NotFoundException::class],
            [409, ['code' => 'duplicate_delivery', 'message' => 'dup', 'metadata' => ['delivery_id' => 'del_9']], ConflictException::class],
            [503, ['code' => 'couriers_busy', 'message' => 'busy'], UberException::class],
        ];

        $sequence = Http::sequence();

        foreach ($cases as [$status, $body]) {
            $sequence->push($body, $status);
        }

        $this->fake([self::BASE.'/*' => $sequence]);

        foreach ($cases as [$status, $body, $class]) {
            try {
                Uber::direct()->create(['manifest_items' => []]);
                $this->fail("Expected {$class} for {$status}");
            } catch (UberException $e) {
                $this->assertInstanceOf($class, $e);
                $this->assertSame($status, $e->getCode());
                $this->assertSame($body['code'], $e->errorCode);
                $this->assertSame($body['metadata'] ?? [], $e->metadata);
                $this->assertStringContainsString($body['message'], $e->getMessage());
            }
        }
    }

    #[Test]
    public function riders_errors_and_surge_confirmations_are_read(): void
    {
        $this->fake([self::BASE.'/v1.2/requests' => Http::response([
            'meta' => ['surge_confirmation' => ['href' => 'https://api.uber.com/surge-confirmations/abc', 'surge_confirmation_id' => 'abc']],
            'errors' => [['status' => 409, 'code' => 'surge', 'title' => 'Surge pricing is currently in effect for this product.']],
        ], 409)]);

        try {
            Uber::withToken('t')->rides()->request(['fare_id' => 'f', 'product_id' => 'p']);
            $this->fail('Expected exception');
        } catch (ConflictException $e) {
            $this->assertSame('surge', $e->errorCode);
            $this->assertSame('https://api.uber.com/surge-confirmations/abc', $e->surgeConfirmationUrl());
            $this->assertSame('abc', $e->surgeConfirmationId());
            $this->assertStringContainsString('Surge pricing', $e->getMessage());
        }
    }

    #[Test]
    public function rate_limits_report_how_long_to_wait(): void
    {
        $this->fake([
            self::BASE.'/v1.2/me' => Http::response(['code' => 'rate_limited'], 429, ['Retry-After' => '12']),
            self::BASE.'/v1.2/payment-methods' => Http::response(['code' => 'rate_limited'], 429, ['X-Rate-Limit-Reset' => (string) (time() + 30)]),
            self::BASE.'/v1.2/history*' => Http::response(['code' => 'rate_limited'], 429),
        ]);

        $waits = [];

        foreach ([fn () => Uber::withToken('t')->rides()->me(), fn () => Uber::withToken('t')->rides()->paymentMethods(), fn () => Uber::withToken('t')->rides()->history()] as $call) {
            try {
                $call();
            } catch (RateLimitException $e) {
                $waits[] = $e->retryAfter();
            }
        }

        $this->assertSame(12, $waits[0]);
        $this->assertEqualsWithDelta(30, $waits[1], 2);
        $this->assertSame(60, $waits[2]);
    }

    #[Test]
    public function reads_are_retried_but_bookings_are_not(): void
    {
        $this->rebuild(['retry' => [3, 0]]);
        $this->fake([
            self::BASE.'/v1/customers/cust-1/deliveries/del_1' => Http::sequence()->push([], 503)->push(['id' => 'del_1']),
            self::BASE.'/v1/customers/cust-1/deliveries' => Http::response([], 503),
        ]);

        $this->assertSame('del_1', Uber::direct()->find('del_1')['id']);

        try {
            Uber::direct()->create(['manifest_items' => []]);
            $this->fail('Expected exception');
        } catch (UberException $e) {
            $this->assertSame(503, $e->getCode());
        }

        $this->assertCount(1, Http::recorded(fn (Request $r) => $r->method() === 'POST' && str_ends_with($r->url(), '/deliveries')));
    }

    #[Test]
    public function each_api_uses_its_own_sandbox_host(): void
    {
        $this->assertSame('https://api.uber.com', Uber::baseUrl());
        $this->assertSame('https://sandbox-api.uber.com', Uber::sandbox()->baseUrl());
        $this->assertSame('https://test-api.uber.com', Uber::sandbox()->baseUrl('eats'));
        $this->assertSame('https://api.uber.com', Uber::sandbox()->baseUrl('direct'));
        $this->assertSame('https://sandbox-login.uber.com', Uber::sandbox()->authUrl('eats'));
        $this->assertSame('https://auth.uber.com', Uber::sandbox()->authUrl('rides'));

        Http::preventStrayRequests();
        Http::fake([
            'https://sandbox-login.uber.com/oauth/v2/token' => $this->tokenResponse('eats-sandbox-token'),
            'https://test-api.uber.com/*' => Http::response([]),
        ]);

        Uber::sandbox()->eats()->stores()->status('s1');

        Http::assertSent(fn (Request $r) => $r->url() === 'https://test-api.uber.com/v1/delivery/store/s1/status'
            && $r->hasHeader('Authorization', 'Bearer eats-sandbox-token'));
    }

    #[Test]
    public function the_base_url_can_be_overridden(): void
    {
        $this->rebuild(['base_url' => 'https://proxy.test/uber/']);

        $this->assertSame('https://proxy.test/uber', Uber::baseUrl('eats'));
    }

    #[Test]
    public function queries_drop_nulls_and_format_dates_and_booleans(): void
    {
        $this->fake();

        Uber::direct()->list(['filter' => 'pending', 'start_dt' => new \DateTimeImmutable('2026-10-01 12:00', new \DateTimeZone('UTC')), 'external_store_id' => null]);
        Uber::guestRides()->trips(includeEditableFields: true);

        Http::assertSent(fn (Request $r) => str_contains($r->url(), '/deliveries?')
            && $r->data() === ['filter' => 'pending', 'start_dt' => '2026-10-01T12:00:00+00:00']);
        Http::assertSent(fn (Request $r) => str_contains($r->url(), '/v1/guests/trips?')
            && str_contains($r->url(), 'include_editable_fields=true'));
    }

    #[Test]
    public function empty_write_bodies_are_sent_as_a_json_object(): void
    {
        $this->fake();

        Uber::eats()->orders()->accept('o1');

        Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/v1/delivery/order/o1/accept') && $r->body() === '{}');
    }

    #[Test]
    public function the_locale_is_sent_as_accept_language(): void
    {
        $this->fake();

        Uber::locale('fr_FR')->withToken('t')->rides()->products(51.5, -0.12);

        Http::assertSent(fn (Request $r) => $r->hasHeader('Accept-Language', 'fr_FR'));
    }

    #[Test]
    public function unwrapped_endpoints_can_be_called_directly(): void
    {
        $this->fake([self::BASE.'/v1/something/new' => Http::response(['ok' => true])]);

        $this->assertTrue(Uber::post('v1/something/new', ['a' => 1], ['some.scope'])['ok']);

        Http::assertSent(fn (Request $r) => $r->url() === self::TOKEN_URL && $r['scope'] === 'some.scope');
    }

    #[Test]
    public function responses_expose_items_dot_paths_and_headers(): void
    {
        $this->fake([self::BASE.'/v1.2/products*' => Http::response(
            ['products' => [['product_id' => 'a', 'price_details' => ['currency_code' => 'GBP']], ['product_id' => 'b']]],
            200,
            ['X-Rate-Limit-Remaining' => '1999'],
        )]);

        $products = Uber::withToken('t')->rides()->products(51.5, -0.12);

        $this->assertCount(2, $products);
        $this->assertSame(['a', 'b'], $products->collect()->pluck('product_id')->all());
        $this->assertSame('GBP', $products->get('products.0.price_details.currency_code'));
        $this->assertSame(1999, $products->rateLimitRemaining());
        $this->assertSame(200, $products->status());
    }
}
