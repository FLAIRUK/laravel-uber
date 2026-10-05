<?php

namespace FLAIRUK\Uber\Tests;

use FLAIRUK\Uber\Exceptions\ConfigurationException;
use FLAIRUK\Uber\Facades\Uber;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;

class ResourcesTest extends TestCase
{
    #[Test]
    public function direct_addresses_are_json_encoded_and_test_deliveries_use_the_robot_courier(): void
    {
        $this->fake();

        $address = ['street_address' => ['20 W 34th St'], 'city' => 'New York', 'state' => 'NY', 'zip_code' => '10001', 'country' => 'US'];

        Uber::direct()->quote(['pickup_address' => $address, 'dropoff_address' => '1 Main St, New York']);
        Uber::direct()->createTest(['pickup_address' => $address, 'manifest_items' => [['name' => 'Box', 'quantity' => 1]]]);

        Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/delivery_quotes')
            && $r['pickup_address'] === '{"street_address":["20 W 34th St"],"city":"New York","state":"NY","zip_code":"10001","country":"US"}'
            && $r['dropoff_address'] === '1 Main St, New York');
        Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/deliveries')
            && $r['test_specifications'] === ['robo_courier_specification' => ['mode' => 'auto']]
            && is_string($r['pickup_address']));
    }

    #[Test]
    public function direct_needs_a_customer_id(): void
    {
        $this->rebuild(['direct.customer_id' => null]);
        Http::preventStrayRequests();

        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage('UBER_DIRECT_CUSTOMER_ID');

        Uber::direct()->find('del_1');
    }

    #[Test]
    public function for_customer_switches_the_direct_account(): void
    {
        $this->fake();

        Uber::forCustomer('cust-2')->direct()->find('del_1');

        Http::assertSent(fn (Request $r) => $r->url() === self::BASE.'/v1/customers/cust-2/deliveries/del_1');
    }

    #[Test]
    public function direct_deliveries_follow_next_href(): void
    {
        $this->fake([self::BASE.'/v1/customers/cust-1/deliveries*' => Http::sequence()
            ->push(['data' => [['id' => 'del_1'], ['id' => 'del_2']], 'next_href' => '/v1/customers/cust-1/deliveries?Offset=2'])
            ->push(['data' => [['id' => 'del_3']], 'next_href' => '']),
        ]);

        $this->assertSame(['del_1', 'del_2', 'del_3'], Uber::direct()->list()->lazy()->pluck('id')->all());

        Http::assertSent(fn (Request $r) => $r->url() === self::BASE.'/v1/customers/cust-1/deliveries?Offset=2');
    }

    #[Test]
    public function guest_trips_follow_next_key_and_send_the_organization(): void
    {
        $this->rebuild(['business.organization_id' => 'org-default']);
        $this->fake([self::BASE.'/v1/guests/trips*' => Http::sequence()
            ->push(['trips' => [['request_id' => 'a']], 'next_key' => 'k2'])
            ->push(['trips' => [['request_id' => 'b']], 'next_key' => '']),
        ]);

        $ids = Uber::guestRides()->forOrganization('org-9')->trips()->lazy()->pluck('request_id')->all();

        $this->assertSame(['a', 'b'], $ids);
        Http::assertSent(fn (Request $r) => str_contains($r->url(), 'start_key=k2') && $r->hasHeader('x-uber-organizationuuid', 'org-9'));
        $this->assertCount(2, array_filter($this->apiRequests(), fn (Request $r) => $r->hasHeader('x-uber-organizationuuid', 'org-9')));
    }

    #[Test]
    public function the_default_organization_is_sent_when_configured(): void
    {
        $this->rebuild(['business.organization_id' => 'org-default']);
        $this->fake();

        Uber::guestRides()->find('t1');
        Uber::business()->receipts()->find('o1');

        $this->assertCount(2, array_filter($this->apiRequests(), fn (Request $r) => $r->hasHeader('x-uber-organizationuuid', 'org-default')));
    }

    #[Test]
    public function no_organization_header_is_sent_when_none_is_configured(): void
    {
        $this->fake();

        Uber::guestRides()->find('t1');

        $this->assertFalse($this->apiRequests()[0]->hasHeader('x-uber-organizationuuid'));
    }

    #[Test]
    public function rider_history_pages_by_offset(): void
    {
        $this->fake([self::BASE.'/v1.2/history*' => Http::sequence()
            ->push(['count' => 3, 'history' => [['request_id' => 'a'], ['request_id' => 'b']]])
            ->push(['count' => 3, 'history' => [['request_id' => 'c']]]),
        ]);

        $ids = Uber::withToken('t')->rides()->history(2)->lazy()->pluck('request_id')->all();

        $this->assertSame(['a', 'b', 'c'], $ids);
        Http::assertSent(fn (Request $r) => str_contains($r->url(), 'offset=2') && str_contains($r->url(), 'limit=2'));
        $this->assertCount(2, $this->apiRequests());
    }

    #[Test]
    public function eats_store_lists_and_orders_follow_page_tokens(): void
    {
        $this->fake([
            self::BASE.'/v1/delivery/stores*' => Http::sequence()
                ->push(['data' => [['id' => 's1']], 'pagination_data' => ['next_page_token' => 'p2']])
                ->push(['data' => [['id' => 's2']], 'pagination_data' => []]),
            self::BASE.'/v1/delivery/store/s1/orders*' => Http::sequence()
                ->push(['data' => [['id' => 'o1']], 'pagination_data' => ['next_page_token' => 'n2']])
                ->push(['data' => [['id' => 'o2']]]),
        ]);

        $this->assertSame(['s1', 's2'], Uber::eats()->stores()->list()->lazy()->pluck('id')->all());
        $this->assertSame(['o1', 'o2'], Uber::eats()->orders()->forStore('s1', ['expand' => ['carts']])->lazy()->pluck('id')->all());

        Http::assertSent(fn (Request $r) => str_contains($r->url(), '/v1/delivery/stores?next_page_token=p2'));
        Http::assertSent(fn (Request $r) => str_contains($r->url(), 'next_page_token=n2') && str_contains($r->url(), 'expand=carts'));
    }

    #[Test]
    public function ads_pages_follow_next_page_token_values(): void
    {
        $this->fake([self::BASE.'/v1/ads/acc1/campaigns*' => Http::sequence()
            ->push(['campaigns' => [['id' => 'c1']], 'next_page_token' => ['value' => 'v2']])
            ->push(['campaigns' => [['id' => 'c2']]]),
        ]);

        $this->assertSame(['c1', 'c2'], Uber::withToken('t')->ads('acc1')->campaigns()->lazy()->pluck('id')->all());
        Http::assertSent(fn (Request $r) => str_contains($r->url(), 'page_token=v2'));
    }

    #[Test]
    public function ads_need_an_account(): void
    {
        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage('UBER_ADS_ACCOUNT_ID');

        Uber::withToken('t')->ads()->campaigns();
    }

    #[Test]
    public function menus_are_uploaded_gzip_compressed(): void
    {
        $this->fake();

        $menu = ['menus' => [['id' => 'm1']], 'items' => [['id' => 'i1', 'title' => ['translations' => ['en_us' => 'Café']]]]];

        Uber::eats()->menus()->replace('s1', $menu);
        Uber::eats()->menus()->replace('s2', $menu, gzip: false);

        Http::assertSent(fn (Request $r) => $r->method() === 'PUT'
            && str_ends_with($r->url(), '/v2/eats/stores/s1/menus')
            && $r->hasHeader('Content-Encoding', 'gzip')
            && $r->hasHeader('Content-Type', 'application/json')
            && json_decode(gzdecode($r->body()), true) === $menu);
        Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/v2/eats/stores/s2/menus')
            && ! $r->hasHeader('Content-Encoding')
            && $r->data() === $menu);
    }

    #[Test]
    public function store_activation_uses_the_merchants_token(): void
    {
        $this->fake();

        Uber::withToken('merchant-token')->eats()->integrations()->activate('s1', ['is_order_manager' => true]);

        Http::assertSent(fn (Request $r) => $r->method() === 'POST'
            && $r->url() === self::BASE.'/v1/eats/stores/s1/pos_data'
            && $r->hasHeader('Authorization', 'Bearer merchant-token')
            && $r['is_order_manager'] === true);
        Http::assertNotSent(fn (Request $r) => $r->url() === self::TOKEN_URL);
    }

    #[Test]
    public function eats_reports_format_dates_per_report_type(): void
    {
        $this->fake();

        Uber::eats()->reports()->create('PAYMENT_DETAILS_REPORT', new \DateTimeImmutable('2026-09-01 00:00:00'), new \DateTimeImmutable('2026-09-07 23:59:59'), ['s1']);
        Uber::eats()->reports()->create('ORDER_HISTORY_REPORT', new \DateTimeImmutable('2026-09-01'), new \DateTimeImmutable('2026-09-07'), groupIds: ['g1']);

        Http::assertSent(fn (Request $r) => ($r->data()['report_type'] ?? null) === 'PAYMENT_DETAILS_REPORT'
            && $r['start_date'] === '2026-09-01T00:00:00'
            && $r['store_uuids'] === ['s1']
            && ! isset($r['group_uuids']));
        Http::assertSent(fn (Request $r) => ($r->data()['report_type'] ?? null) === 'ORDER_HISTORY_REPORT'
            && $r['end_date'] === '2026-09-07'
            && $r['group_uuids'] === ['g1']);
    }

    #[Test]
    public function charges_and_payouts_carry_idempotency_keys(): void
    {
        $this->fake();

        Uber::eats()->payments()->charge(['payment_code' => 'c'], 'key-1');
        Uber::eats()->payments()->charge(['payment_code' => 'c']);
        Uber::payments()->finalizePayout('p1', [], 'key-2');

        $requests = $this->apiRequests();
        $this->assertTrue($requests[0]->hasHeader('Idempotency-Key', 'key-1'));
        $this->assertNotEmpty($requests[1]->header('Idempotency-Key')[0]);
        $this->assertTrue($requests[2]->hasHeader('X-Idempotency-Key', 'key-2'));
    }

    #[Test]
    public function sandbox_controls_refuse_to_run_against_production(): void
    {
        Http::preventStrayRequests();

        foreach ([
            fn () => Uber::withToken('t')->rides()->sandbox(),
            fn () => Uber::guestRides()->sandbox(),
            fn () => Uber::business()->receipts('org')->createSandboxRun([]),
            fn () => Uber::business()->employeeTrips('org')->createSandboxRun([]),
        ] as $call) {
            try {
                $call();
                $this->fail('Expected a ConfigurationException');
            } catch (ConfigurationException $e) {
                $this->assertStringContainsString('sandbox', $e->getMessage());
            }
        }
    }

    #[Test]
    public function sandbox_rides_and_guest_runs_hit_the_sandbox_host(): void
    {
        $this->fake();

        Uber::sandbox()->withToken('t')->rides()->sandbox()->setStatus('r1', 'accepted');
        Uber::sandbox()->withToken('t')->rides()->sandbox()->setProduct('p1', surgeMultiplier: 2.2);
        Uber::sandbox()->guestRides()->sandbox()->driverState('run1', 'd1', 'ACCEPT');
        Uber::sandbox()->guestRides()->inSandboxRun('run1')->estimates(['pickup' => []]);
        Uber::sandbox()->health()->sandbox()->createRun(['pickup_location' => []]);

        Http::assertSent(fn (Request $r) => $r->method() === 'PUT' && $r->url() === 'https://sandbox-api.uber.com/v1.2/sandbox/requests/r1' && $r['status'] === 'accepted');
        Http::assertSent(fn (Request $r) => $r->url() === 'https://sandbox-api.uber.com/v1.2/sandbox/products/p1' && $r->data() === ['surge_multiplier' => 2.2]);
        Http::assertSent(fn (Request $r) => $r->url() === 'https://sandbox-api.uber.com/v1/guests/sandbox/driver-state' && $r['driver_state'] === 'ACCEPT');
        Http::assertSent(fn (Request $r) => $r->url() === 'https://sandbox-api.uber.com/v1/guests/trips/estimates' && $r->hasHeader('x-uber-sandbox-runuuid', 'run1'));
        Http::assertSent(fn (Request $r) => $r->url() === 'https://sandbox-api.uber.com/v1/health/sandbox/run');
    }

    #[Test]
    public function the_health_scope_can_be_configured_for_the_sandbox(): void
    {
        $this->rebuild(['health.scope' => 'health.sandbox']);
        $this->fake();

        Uber::health()->find('t1');

        Http::assertSent(fn (Request $r) => $r->url() === self::TOKEN_URL && $r['scope'] === 'health.sandbox');
    }

    #[Test]
    public function shifts_take_dates_as_utc_milliseconds(): void
    {
        $this->fake();

        Uber::vehicleSuppliers()->shifts()->save(
            'e1',
            new \DateTimeImmutable('2026-10-05 09:00:00', new \DateTimeZone('UTC')),
            new \DateTimeImmutable('2026-10-05 17:00:00', new \DateTimeZone('UTC')),
            'zone-1',
        );

        Http::assertSent(fn (Request $r) => ($r->data()['shift'] ?? null) === [
            'start_time_utc' => ['value' => 1791190800000],
            'end_time_utc' => ['value' => 1791219600000],
            'zone_id' => 'zone-1',
            'marketplace_type' => 'MARKETPLACE_TYPE_RIDES',
        ]);
    }

    #[Test]
    public function vehicle_documents_are_base64_encoded(): void
    {
        $this->fake();

        Uber::vehicleSuppliers()->vehicles()->uploadDocument('v1', 'INSURANCE', '%PDF-1.7', 'application/pdf', '2027-01-01');

        Http::assertSent(fn (Request $r) => ($r->data()['content'] ?? null) === base64_encode('%PDF-1.7') && $r['document_expiry_date'] === '2027-01-01');
    }

    #[Test]
    public function the_business_consent_url_names_the_app(): void
    {
        $url = Uber::business()->consentUrl(['business.receipts', 'guests.trips'], 'https://app.test/uber/consent', 'Acme Travel');

        $this->assertSame(
            'https://business.uber.com/authorize?client_id=test-client&scope=business.receipts%20guests.trips&redirect_uri=https%3A%2F%2Fapp.test%2Fuber%2Fconsent&app_name=Acme%20Travel',
            $url,
        );
    }

    #[Test]
    public function unlinking_sends_this_apps_client_id(): void
    {
        $this->fake();

        Uber::identity()->unlinkAccount('user-42');

        Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/v1/identity/unlink-account')
            && $r->data() === ['thirdPartyUserID' => 'user-42', 'clientID' => 'test-client']);
    }
}
