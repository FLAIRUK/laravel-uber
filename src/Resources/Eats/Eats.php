<?php

namespace FLAIRUK\Uber\Resources\Eats;

use FLAIRUK\Uber\Resources\Resource;

/**
 * Uber Eats Marketplace: for point-of-sale and order-management systems that run
 * restaurants and shops on Uber Eats.
 *
 * App tokens throughout, except store discovery and activation, which need the
 * merchant's own token (authorization code, scope eats.pos_provisioning):
 * Uber::withToken($merchantToken)->eats()->integrations()->activate(...).
 *
 * @see https://developer.uber.com/docs/eats/introduction
 */
class Eats extends Resource
{
    protected string $api = 'eats';

    public function stores(): Stores
    {
        return new Stores($this->client);
    }

    public function menus(): Menus
    {
        return new Menus($this->client);
    }

    /**
     * The Order Fulfillment API (/v1/delivery).
     */
    public function orders(): Orders
    {
        return new Orders($this->client);
    }

    /**
     * The previous order endpoints (/v1/eats and /v2/eats/order), for stores still on them.
     */
    public function legacyOrders(): LegacyOrders
    {
        return new LegacyOrders($this->client);
    }

    /**
     * Activate, configure and remove your app's integration with a store (pos_data).
     */
    public function integrations(): Integrations
    {
        return new Integrations($this->client);
    }

    public function promotions(): Promotions
    {
        return new Promotions($this->client);
    }

    public function reports(): Reports
    {
        return new Reports($this->client);
    }

    /**
     * Paymentless Checkout: take payment codes from the Uber app at your till.
     */
    public function payments(int $version = 2): PaymentlessCheckout
    {
        return new PaymentlessCheckout($this->client, $version);
    }

    /**
     * Bring your own courier: send your courier's live position for an order.
     */
    public function couriers(): Couriers
    {
        return new Couriers($this->client);
    }
}
