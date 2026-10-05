<?php

namespace FLAIRUK\Uber\Resources\Drivers;

use FLAIRUK\Uber\Resources\Resource;
use FLAIRUK\Uber\Response;

/**
 * Drivers API: a driver's own profile, earnings and trips, with their consent
 * (user token: Uber::withToken($token)->drivers()). Access is limited; apply to Uber.
 * Scopes partner.accounts, partner.payments, partner.trips.
 *
 * @see https://developer.uber.com/docs/drivers/introduction
 */
class Drivers extends Resource
{
    public function me(): Response
    {
        return $this->get('v1/partners/me');
    }

    /**
     * Earnings, newest first. Call ->lazy() on the result to walk every page.
     *
     * @param  array<string, mixed>  $filters  ['from_time', 'to_time'] (Unix seconds)
     */
    public function payments(array $filters = [], int $limit = 50): Response
    {
        return $this->offsetPaginated('v1/partners/payments', $filters, 'payments', $limit);
    }

    /**
     * Trips, newest first. Call ->lazy() on the result to walk every page.
     *
     * @param  array<string, mixed>  $filters  ['from_time', 'to_time'] (Unix seconds)
     */
    public function trips(array $filters = [], int $limit = 50): Response
    {
        return $this->offsetPaginated('v1/partners/trips', $filters, 'trips', $limit);
    }
}
