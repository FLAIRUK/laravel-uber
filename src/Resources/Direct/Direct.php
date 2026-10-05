<?php

namespace FLAIRUK\Uber\Resources\Direct;

use FLAIRUK\Uber\Exceptions\ConfigurationException;
use FLAIRUK\Uber\Resources\Resource;
use FLAIRUK\Uber\Response;

/**
 * Uber Direct: dispatch an Uber courier from your store to your customer.
 *
 * App token, scope eats.deliveries (direct.organizations for organizations and
 * business locations). Test and production are separate Direct accounts with their
 * own credentials and customer id; test deliveries can use the robot courier.
 *
 * @see https://developer.uber.com/docs/deliveries/overview
 */
class Direct extends Resource
{
    public const STATUSES = ['pending', 'pickup', 'pickup_complete', 'dropoff', 'delivered', 'canceled', 'returned'];

    public const CANCELATION_REASONS = [
        'out_of_items', 'store_closed', 'customer_called_to_cancel', 'store_too_busy',
        'courier_delayed_en_route_to_pickup', 'too_expensive', 'delivery_vehicle_too_small',
        'no_courier_assigned', 'other',
    ];

    protected array $scopes = ['eats.deliveries'];

    protected string $api = 'direct';

    /**
     * A price and ETA for a delivery, valid for a few minutes. Pass its id as quote_id to create().
     *
     * Addresses can be arrays (['street_address' => [...], 'city', 'state', 'zip_code', 'country'])
     * and are JSON-encoded for you, as Uber expects. Use the same format on the quote and the delivery.
     *
     * @param  array<string, mixed>  $quote  ['pickup_address', 'dropoff_address', 'pickup_latitude'?, ..., 'manifest_total_value'?, 'external_store_id'?]
     */
    public function quote(array $quote): Response
    {
        return $this->post($this->customerPath('delivery_quotes'), $this->encodeAddresses($quote), retry: true);
    }

    /**
     * Book a courier. Never retried automatically; send an idempotency_key so that
     * retrying yourself can't book twice (a 409 duplicate_delivery carries the existing id).
     *
     * @param  array<string, mixed>  $delivery  ['pickup_name', 'pickup_address', 'pickup_phone_number', 'dropoff_name', 'dropoff_address', 'dropoff_phone_number', 'manifest_items', 'quote_id'?, 'idempotency_key'?, 'external_id'?, ...]
     */
    public function create(array $delivery): Response
    {
        return $this->post($this->customerPath('deliveries'), $this->encodeAddresses($delivery));
    }

    /**
     * Book a test delivery that the robot courier completes by itself, a step about every 30 seconds.
     * Use custom timings with ['mode' => 'custom', 'enroute_for_pickup_at' => ..., ...].
     *
     * @param  array<string, mixed>  $delivery
     * @param  array<string, mixed>  $robot
     */
    public function createTest(array $delivery, array $robot = ['mode' => 'auto']): Response
    {
        return $this->create($delivery + ['test_specifications' => ['robo_courier_specification' => $robot]]);
    }

    public function find(string $deliveryId): Response
    {
        return $this->get($this->customerPath('deliveries/'.$this->segment($deliveryId)));
    }

    /**
     * Deliveries, newest first. Call ->lazy() on the result to walk every page.
     *
     * @param  array<string, mixed>  $filters  ['filter' => 'pending'|'pickup'|..., 'external_store_id', 'start_dt', 'end_dt', 'limit', 'offset']
     */
    public function list(array $filters = []): Response
    {
        if (array_key_exists('offset', $filters)) {
            $filters['Offset'] = $filters['offset'];   // the API spells it with a capital O
            unset($filters['offset']);
        }

        return $this->get($this->customerPath('deliveries'), $filters)
            ->paginate('data', function (Response $page) {
                $next = $page->get('next_href');

                return filled($next) ? $this->nextPage((string) $next) : null;
            });
    }

    /**
     * Change notes, verification, a raised tip, the dropoff point or the windows.
     *
     * @param  array<string, mixed>  $changes
     */
    public function update(string $deliveryId, array $changes): Response
    {
        return $this->post($this->customerPath('deliveries/'.$this->segment($deliveryId)), $changes);
    }

    /**
     * Cancel a delivery. A reason of "other" needs a description.
     */
    public function cancel(string $deliveryId, ?string $reason = null, ?string $description = null): Response
    {
        return $this->post($this->customerPath('deliveries/'.$this->segment($deliveryId).'/cancel'), array_filter([
            'cancelation_reason' => $reason,
            'additional_description' => $description,
        ]));
    }

    /**
     * The proof-of-delivery image (base64 in "document").
     *
     * @param  string  $waypoint  pickup, dropoff or return
     * @param  string  $type  picture, signature or pincode
     */
    public function proofOfDelivery(string $deliveryId, string $waypoint = 'dropoff', string $type = 'picture'): Response
    {
        return $this->post($this->customerPath('deliveries/'.$this->segment($deliveryId).'/proof-of-delivery'), [
            'waypoint' => $waypoint,
            'type' => $type,
        ], retry: true);
    }

    /**
     * Your stores (business locations) near a point.
     */
    public function stores(float $latitude, float $longitude): Response
    {
        return $this->get('v1/direct/organizations/'.$this->segment($this->customerId()).'/stores', [
            'latitude' => $latitude,
            'longitude' => $longitude,
        ])->paginate('stores');
    }

    /**
     * Ask Uber for a refund on a delivery (if enabled for your account). Fails with 409 if one was already submitted.
     *
     * @param  array<string, mixed>  $refund  ['delivery_id', 'requester_email_id', 'refund_reason' => 'uber_missing_items'|..., 'total_refund_amount' => ['amount', 'currency_code'], 'items_missing'?, 'notes'?, 'cc_email_ids'?]
     */
    public function refund(array $refund): Response
    {
        return $this->post('v1/direct/'.$this->segment($this->customerId()).'/submit_refund', $refund);
    }

    /**
     * Sub-organisations (one per merchant or store) and their members.
     */
    public function organizations(): Organizations
    {
        return new Organizations($this->client);
    }

    /**
     * Business locations: the stores external_store_id refers to.
     */
    public function businessLocations(?string $organizationId = null): BusinessLocations
    {
        return new BusinessLocations($this->client, $organizationId ?? $this->customerId());
    }

    public function customerId(): string
    {
        return filled($id = $this->client->config('direct.customer_id'))
            ? (string) $id
            : throw new ConfigurationException('No Uber Direct customer id: set UBER_DIRECT_CUSTOMER_ID or call Uber::forCustomer().');
    }

    protected function customerPath(string $path): string
    {
        return 'v1/customers/'.$this->segment($this->customerId()).'/'.$path;
    }

    protected function nextPage(string $href): Response
    {
        $url = str_starts_with($href, 'https://') ? $href : $this->client->baseUrl('direct').'/'.ltrim($href, '/');

        return $this->get($url)->paginate('data', function (Response $page) {
            $next = $page->get('next_href');

            return filled($next) ? $this->nextPage((string) $next) : null;
        });
    }

    /**
     * Uber wants structured addresses as JSON strings inside the JSON body.
     *
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>
     */
    protected function encodeAddresses(array $body): array
    {
        foreach (['pickup_address', 'dropoff_address'] as $key) {
            if (isset($body[$key]) && is_array($body[$key])) {
                $body[$key] = json_encode($body[$key], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            }
        }

        return $body;
    }
}
