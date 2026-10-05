<?php

namespace FLAIRUK\Uber\Resources\Rides;

use FLAIRUK\Uber\Resources\Resource;
use FLAIRUK\Uber\Response;

/**
 * Riders API (v1.2): products, estimates and ride requests for a rider.
 *
 * Every endpoint acts for a user: authorize them with Uber::oauth() and call
 * Uber::withToken($token)->rides(). Scopes: profile, history, places, request
 * (privileged), request_receipt, all_trips, offline_access.
 *
 * Uber's current reference documents products, estimates, me, payment methods and
 * creating a request. Methods marked "legacy" call endpoints whose reference pages
 * Uber has since taken down; they are kept because existing integrations rely on them.
 *
 * @see https://developer.uber.com/docs/riders/references/api
 */
class Rides extends Resource
{
    protected const VERSION = 'v1.2';

    /**
     * Products available at a location.
     */
    public function products(float $latitude, float $longitude): Response
    {
        return $this->get(self::VERSION.'/products', ['latitude' => $latitude, 'longitude' => $longitude])
            ->paginate('products');
    }

    /**
     * One product. Legacy.
     */
    public function product(string $productId): Response
    {
        return $this->get(self::VERSION.'/products/'.$this->segment($productId));
    }

    /**
     * Price ranges per product for a trip. Needs approval from Uber.
     *
     * @param  int|null  $seatCount  for shared products, 1 or 2
     */
    public function priceEstimates(float $startLatitude, float $startLongitude, float $endLatitude, float $endLongitude, ?int $seatCount = null): Response
    {
        return $this->get(self::VERSION.'/estimates/price', [
            'start_latitude' => $startLatitude,
            'start_longitude' => $startLongitude,
            'end_latitude' => $endLatitude,
            'end_longitude' => $endLongitude,
            'seat_count' => $seatCount,
        ])->paginate('prices');
    }

    /**
     * Pickup ETAs in seconds per product. Needs approval from Uber.
     */
    public function timeEstimates(float $startLatitude, float $startLongitude, ?string $productId = null): Response
    {
        return $this->get(self::VERSION.'/estimates/time', [
            'start_latitude' => $startLatitude,
            'start_longitude' => $startLongitude,
            'product_id' => $productId,
        ])->paginate('times');
    }

    /**
     * An upfront fare for one product, with the fare_id that request() needs. Legacy.
     *
     * @param  array<string, mixed>  $trip  ['product_id', 'start_latitude', 'start_longitude', 'end_latitude', 'end_longitude', 'seat_count'?]
     */
    public function estimate(array $trip): Response
    {
        return $this->post(self::VERSION.'/requests/estimate', $trip, retry: true);
    }

    /**
     * Request a ride (scope: request). Never retried automatically: if it times out,
     * check current() before trying again.
     *
     * A ConflictException with errorCode "surge" carries surgeConfirmationUrl(): send the
     * rider there, then request again with the surge_confirmation_id.
     *
     * @param  array<string, mixed>  $ride  ['fare_id', 'product_id', 'start_latitude', 'start_longitude', 'end_latitude', 'end_longitude', 'payment_method_id'?, 'seat_count'?, 'expense_code'?, 'expense_memo'?]
     */
    public function request(array $ride): Response
    {
        return $this->post(self::VERSION.'/requests', $ride);
    }

    /**
     * The rider's trip in progress. Legacy.
     */
    public function current(): Response
    {
        return $this->get(self::VERSION.'/requests/current');
    }

    /**
     * A ride request's status, driver, vehicle and location. Legacy.
     */
    public function find(string $requestId): Response
    {
        return $this->get(self::VERSION.'/requests/'.$this->segment($requestId));
    }

    /**
     * Change the destination of a ride. Legacy.
     *
     * @param  array<string, mixed>  $changes  e.g. ['end_latitude' => ..., 'end_longitude' => ...]
     */
    public function update(string $requestId, array $changes): Response
    {
        return $this->patch(self::VERSION.'/requests/'.$this->segment($requestId), $changes);
    }

    /**
     * Cancel a ride. Cancellation fees may apply.
     */
    public function cancel(string $requestId): Response
    {
        return $this->delete(self::VERSION.'/requests/'.$this->segment($requestId));
    }

    /**
     * Cancel the rider's current trip. Legacy.
     */
    public function cancelCurrent(): Response
    {
        return $this->delete(self::VERSION.'/requests/current');
    }

    /**
     * A link to a live map of the trip. Legacy.
     */
    public function map(string $requestId): Response
    {
        return $this->get(self::VERSION.'/requests/'.$this->segment($requestId).'/map');
    }

    /**
     * The receipt for a completed trip your app requested (scope: request_receipt). Legacy.
     */
    public function receipt(string $requestId): Response
    {
        return $this->get(self::VERSION.'/requests/'.$this->segment($requestId).'/receipt');
    }

    /**
     * The rider's profile (scope: profile).
     */
    public function me(): Response
    {
        return $this->get(self::VERSION.'/me');
    }

    /**
     * Apply a promotion code to the rider's account.
     */
    public function applyPromotion(string $code): Response
    {
        return $this->patch(self::VERSION.'/me', ['applied_promotion_codes' => $code]);
    }

    /**
     * The rider's past trips (scope: history or history_lite). Legacy.
     *
     * Call ->lazy() on the result to walk every page.
     */
    public function history(int $limit = 50, int $offset = 0): Response
    {
        return $this->offsetPaginated(self::VERSION.'/history', ['offset' => $offset], 'history', $limit);
    }

    /**
     * The rider's payment methods and the last one used (scope: request).
     */
    public function paymentMethods(): Response
    {
        return $this->get(self::VERSION.'/payment-methods')->paginate('payment_methods');
    }

    /**
     * A saved place: "home" or "work" (scope: places). Legacy.
     */
    public function place(string $placeId): Response
    {
        return $this->get(self::VERSION.'/places/'.$this->segment($placeId));
    }

    /**
     * Save the address of "home" or "work" (scope: places). Legacy.
     */
    public function updatePlace(string $placeId, string $address): Response
    {
        return $this->put(self::VERSION.'/places/'.$this->segment($placeId), ['address' => $address]);
    }

    /**
     * Sandbox controls. Only work against sandbox-api.uber.com.
     */
    public function sandbox(): RidesSandbox
    {
        return new RidesSandbox($this->client);
    }
}
