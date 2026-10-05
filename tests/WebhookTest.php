<?php

namespace FLAIRUK\Uber\Tests;

use FLAIRUK\Uber\Events\WebhookReceived;
use FLAIRUK\Uber\Exceptions\ConfigurationException;
use FLAIRUK\Uber\Webhooks\WebhookEvent;
use FLAIRUK\Uber\Webhooks\WebhookSignature;
use Illuminate\Support\Facades\Event;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;

class WebhookTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('uber.webhooks.signing_keys', ['guest-key', 'direct-key']);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, string>  $headers
     */
    protected function deliver(array $payload, ?string $key = 'test-secret', string $header = 'X-Uber-Signature', array $headers = []): TestResponse
    {
        $body = json_encode($payload);
        $server = ['CONTENT_TYPE' => 'application/json'];

        if ($key !== null) {
            $server['HTTP_'.strtoupper(str_replace('-', '_', $header))] = hash_hmac('sha256', $body, $key);
        }

        foreach ($headers as $name => $value) {
            $server['HTTP_'.strtoupper(str_replace('-', '_', $name))] = $value;
        }

        return $this->call('POST', '/uber/webhook', [], [], [], $server, $body);
    }

    #[Test]
    public function riders_and_eats_webhooks_signed_with_the_client_secret_are_dispatched(): void
    {
        Event::fake();

        $this->deliver([
            'event_id' => 'evt-1',
            'event_time' => 1791190800,
            'event_type' => 'orders.notification',
            'meta' => ['user_id' => 'store-1', 'resource_id' => 'order-1', 'status' => 'pos'],
            'resource_href' => 'https://api.uber.com/v1/delivery/order/order-1',
        ], headers: ['X-Environment' => 'sandbox'])->assertOk()->assertContent('');

        Event::assertDispatched(WebhookReceived::class, fn (WebhookReceived $e) => $e->event->type === 'orders.notification'
            && $e->event->id === 'evt-1'
            && $e->event->resourceId() === 'order-1'
            && $e->event->status() === 'pos'
            && $e->event->isSandbox()
            && $e->event->meta('user_id') === 'store-1');
        Event::assertDispatched('uber.orders.notification', fn (string $name, array $payload) => $payload[0] instanceof WebhookEvent);
    }

    #[Test]
    public function direct_webhooks_signed_with_a_signing_key_are_read(): void
    {
        Event::fake();

        $this->deliver([
            'id' => 'evt-d1',
            'kind' => 'event.delivery_status',
            'status' => 'pickup',
            'delivery_id' => 'del_1',
            'live_mode' => false,
            'created' => '2026-10-05T09:00:00Z',
            'data' => ['courier_imminent' => true],
        ], key: 'direct-key', header: 'X-Postmates-Signature')->assertOk();

        Event::assertDispatched('uber.event.delivery_status', fn (string $name, array $payload) => $payload[0]->resourceId() === 'del_1'
            && $payload[0]->status() === 'pickup'
            && $payload[0]->environment === 'sandbox'
            && $payload[0]->get('data.courier_imminent') === true
            && $payload[0]->time === 1791190800);
    }

    #[Test]
    public function guest_rides_webhooks_signed_with_another_key_are_accepted(): void
    {
        Event::fake();

        $this->deliver(['event_id' => 'evt-g1', 'event_type' => 'guests.trips.status_changed', 'meta' => ['status' => 'accepted']], key: 'guest-key')
            ->assertOk();

        Event::assertDispatched('uber.guests.trips.status_changed');
    }

    #[Test]
    public function bad_or_missing_signatures_are_rejected(): void
    {
        Event::fake();

        $this->deliver(['event_type' => 'orders.notification'], key: 'wrong-key')->assertForbidden();
        $this->deliver(['event_type' => 'orders.notification'], key: null)->assertForbidden();

        Event::assertNotDispatched(WebhookReceived::class);
    }

    #[Test]
    public function repeated_deliveries_are_dispatched_once(): void
    {
        Event::fake();

        $payload = ['event_id' => 'evt-dup', 'event_type' => 'requests.status_changed'];

        $this->deliver($payload)->assertOk();
        $this->deliver($payload)->assertOk();

        Event::assertDispatchedTimes(WebhookReceived::class, 1);
    }

    #[Test]
    public function a_failing_listener_lets_uber_retry_the_event(): void
    {
        $calls = 0;
        Event::listen('uber.requests.status_changed', function () use (&$calls) {
            if (++$calls === 1) {
                throw new \RuntimeException('database down');
            }
        });

        $payload = ['event_id' => 'evt-retry', 'event_type' => 'requests.status_changed'];

        $this->deliver($payload)->assertServerError();
        $this->deliver($payload)->assertOk();

        $this->assertSame(2, $calls);
    }

    #[Test]
    public function invalid_json_is_rejected(): void
    {
        $body = 'not json';

        $this->call('POST', '/uber/webhook', [], [], [], [
            'HTTP_X_UBER_SIGNATURE' => hash_hmac('sha256', $body, 'test-secret'),
        ], $body)->assertStatus(400);
    }

    #[Test]
    public function the_signature_compares_case_insensitively_and_needs_a_key(): void
    {
        $signature = new WebhookSignature(['k']);
        $sig = $signature->sign('{"a":1}');

        $this->assertTrue($signature->verify('{"a":1}', strtoupper($sig)));
        $this->assertFalse($signature->verify('{"a":2}', $sig));
        $this->assertFalse($signature->verify('', $sig));

        $this->expectException(ConfigurationException::class);
        (new WebhookSignature(['', '']))->verify('{"a":1}', $sig);
    }

    #[Test]
    public function business_webhook_meta_supplies_the_id_and_time(): void
    {
        $event = WebhookEvent::fromPayload([
            'event_type' => 'business_order.receipt',
            'meta' => ['order_id' => 'o1', 'status' => 'CREATED'],
            'webhook_meta' => ['webhook_msg_uuid' => 'msg-1', 'webhook_msg_timestamp' => 1791190800],
        ], '{}');

        $this->assertSame('msg-1', $event->id);
        $this->assertSame(1791190800, $event->time);
        $this->assertSame('o1', $event->resourceId());
        $this->assertSame('CREATED', $event->status());
    }
}
