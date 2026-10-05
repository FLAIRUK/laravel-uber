<?php

namespace FLAIRUK\Uber\Resources\GuestRides;

use FLAIRUK\Uber\Response;

/**
 * Guest Rides (Uber for Business / Uber Central): order rides for guests who
 * need no Uber account, billed to your organisation.
 *
 * App token, scope guests.trips. Third-party apps acting for another
 * organisation send its UUID: set UBER_ORGANIZATION_ID or call forOrganization().
 *
 * @see https://developer.uber.com/docs/guest-rides/introduction
 */
class GuestRides extends ManagedRides
{
    protected string $prefix = 'guests';

    protected array $scopes = ['guests.trips'];

    protected string $api = 'guests';

    /**
     * Products available at a pickup point, with fare, reservation and cancellation
     * info. From the previous version of the reference; not in the current spec.
     *
     * @param  array<string, mixed>  $pickup  e.g. ['point' => ['latitude' => ..., 'longitude' => ...]]
     */
    public function productsInfo(array $pickup): Response
    {
        return $this->post('v1/trips/products-info', ['pickup' => $pickup], retry: true);
    }
}
