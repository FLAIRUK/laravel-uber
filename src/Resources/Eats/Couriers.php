<?php

namespace FLAIRUK\Uber\Resources\Eats;

use FLAIRUK\Uber\Resources\Resource;
use FLAIRUK\Uber\Response;

/**
 * Bring your own courier (BYOC): report your courier's live location so the
 * eater can track the order (scope eats.byoc.position).
 *
 * @see https://developer.uber.com/docs/eats/references/api/delivery_byoc_suite
 */
class Couriers extends Resource
{
    protected array $scopes = ['eats.byoc.position'];

    protected string $api = 'eats';

    /**
     * @param  array<string, mixed>  $locationRequest  the order id, courier and position, as documented
     */
    public function location(array $locationRequest): Response
    {
        return $this->post('v1/eats/byoc/restaurants/orders/event/location', ['location_request' => $locationRequest], retry: true);
    }
}
