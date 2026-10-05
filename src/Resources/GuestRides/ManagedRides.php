<?php

namespace FLAIRUK\Uber\Resources\GuestRides;

use FLAIRUK\Uber\Resources\Resource;
use FLAIRUK\Uber\Response;

/**
 * The rides-for-others APIs that Guest Rides and Uber Health share: estimates,
 * trips, receipts, driver contact, zones and address lookup, under /v1/{prefix}.
 */
abstract class ManagedRides extends Resource
{
    /**
     * The path segment: "guests" or "health".
     */
    protected string $prefix;

    public const STATUSES = [
        'processing', 'no_drivers_available', 'accepted', 'arriving', 'in_progress', 'driver_canceled',
        'rider_canceled', 'completed', 'scheduled', 'failed', 'offered', 'expired', 'driver_redispatched',
    ];

    protected ?string $organizationId = null;

    protected ?string $sandboxRunId = null;

    /**
     * Act for another Uber for Business organisation (x-uber-organizationuuid).
     */
    public function forOrganization(string $organizationId): static
    {
        $clone = clone $this;
        $clone->organizationId = $organizationId;

        return $clone;
    }

    /**
     * Send estimates and trips into a sandbox run (x-uber-sandbox-runuuid). See sandbox()->createRun().
     */
    public function inSandboxRun(string $runId): static
    {
        $clone = clone $this;
        $clone->sandboxRunId = $runId;

        return $clone;
    }

    /**
     * Products, fares (with the fare_id for create()) and pickup ETAs for a route.
     *
     * @param  array<string, mixed>  $route  ['pickup' => ['latitude', 'longitude'], 'dropoff' => [...], 'scheduling'?, 'waypoints'?]
     */
    public function estimates(array $route): Response
    {
        return $this->post('v1/'.$this->prefix.'/trips/estimates', $route, retry: true)->paginate('product_estimates');
    }

    /**
     * Order a ride for a guest. Never retried automatically: if it times out, check trips() before trying again.
     *
     * @param  array<string, mixed>  $trip  ['guest' => ['first_name', 'last_name', 'phone_number'], 'pickup', 'dropoff', 'product_id', 'fare_id'?, 'scheduling'?, 'note_for_driver'?, 'expense_memo'?]
     */
    public function create(array $trip): Response
    {
        return $this->post('v1/'.$this->prefix.'/trips', $trip);
    }

    /**
     * Trips by status: ACTIVE (default), PAST or EXPIRED. Call ->lazy() on the result to walk every page.
     */
    public function trips(string $status = 'ACTIVE', int $limit = 50, ?string $startKey = null, bool $includeEditableFields = false): Response
    {
        $query = [
            'trip_status' => $status,
            'limit' => $limit,
            'start_key' => $startKey,
            'include_editable_fields' => $includeEditableFields ?: null,
        ];

        return $this->get('v1/'.$this->prefix.'/trips', $query)->paginate('trips', function (Response $page) use ($status, $limit, $includeEditableFields) {
            $next = $page->get('next_key');

            return filled($next) ? $this->trips($status, $limit, $next, $includeEditableFields) : null;
        });
    }

    /**
     * A trip's status, driver, vehicle, location and tracking URL.
     */
    public function find(string $requestId): Response
    {
        return $this->get('v1/'.$this->prefix.'/trips/'.$this->segment($requestId));
    }

    /**
     * Change the dropoff, stops, notes or (flexible rides) pickup and product.
     *
     * @param  array<string, mixed>  $changes
     */
    public function update(string $requestId, array $changes): Response
    {
        return $this->put('v1/'.$this->prefix.'/trips/'.$this->segment($requestId), $changes);
    }

    public function cancel(string $requestId): Response
    {
        return $this->delete('v1/'.$this->prefix.'/trips/'.$this->segment($requestId));
    }

    /**
     * The receipt. A NotFoundException until it is ready, and always in the sandbox.
     */
    public function receipt(string $requestId): Response
    {
        return $this->get('v1/'.$this->prefix.'/trips/'.$this->segment($requestId).'/receipt');
    }

    /**
     * Dispatch a flexible ride on its pickup day.
     */
    public function dispatch(string $requestId): Response
    {
        return $this->post('v1/'.$this->prefix.'/trips/'.$this->segment($requestId).'/dispatch');
    }

    /**
     * Message the driver. Fails until a driver is assigned.
     */
    public function message(string $requestId, string $message): Response
    {
        return $this->post('v1/'.$this->prefix.'/trips/'.$this->segment($requestId).'/message', ['message' => $message]);
    }

    /**
     * A proxy number that connects this phone number to the driver.
     */
    public function communication(string $requestId, string $phoneNumber): Response
    {
        return $this->put('v1/'.$this->prefix.'/trips/'.$this->segment($requestId).'/communication', ['phone_number' => $phoneNumber]);
    }

    /**
     * Have Uber call this phone number and connect it to the driver.
     */
    public function call(string $requestId, string $phoneNumber): Response
    {
        return $this->post('v1/'.$this->prefix.'/trips/call', ['request_id' => $requestId, 'phone_number' => $phoneNumber]);
    }

    /**
     * Tip the driver after the trip. Production only.
     */
    public function tip(string $requestId, float $amount): Response
    {
        return $this->post('v1/'.$this->prefix.'/trips/tip', ['request_id' => $requestId, 'tip_amount' => $amount]);
    }

    /**
     * Pickup zones and access points (e.g. airport pickup spots) near a location.
     *
     * @param  array<string, mixed>  $options  ['place_id', 'place_provider', 'address', 'product_id']
     */
    public function zones(float $latitude, float $longitude, array $options = []): Response
    {
        return $this->get('v1/'.$this->prefix.'/zones', ['latitude' => $latitude, 'longitude' => $longitude] + $options);
    }

    /**
     * Address suggestions for at least four characters of input.
     */
    public function autocomplete(string $query, ?float $latitude = null, ?float $longitude = null, ?string $locale = null): Response
    {
        return $this->get('v1/'.$this->prefix.'/address/autocomplete', [
            'query' => $query,
            'latitude' => $latitude,
            'longitude' => $longitude,
            'locale' => $locale,
        ])->paginate('addresses');
    }

    /**
     * Whether a phone number is a mobile that can receive SMS trip updates.
     */
    public function phoneInfo(string $phoneNumber): Response
    {
        return $this->get($this->phoneInfoPath(), ['phone_number' => $phoneNumber]);
    }

    /**
     * Sandbox runs and test drivers. Only work against sandbox-api.uber.com.
     */
    public function sandbox(): ManagedRidesSandbox
    {
        return new ManagedRidesSandbox($this->client, $this->prefix, $this->scopes, $this->organizationId);
    }

    protected function phoneInfoPath(): string
    {
        return 'v1/'.$this->prefix.'/guests/phone-info';
    }

    protected function headers(): array
    {
        return [
            'x-uber-organizationuuid' => $this->organizationId ?? $this->client->config('business.organization_id'),
            'x-uber-sandbox-runuuid' => $this->sandboxRunId,
        ];
    }
}
