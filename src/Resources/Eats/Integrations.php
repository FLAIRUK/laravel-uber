<?php

namespace FLAIRUK\Uber\Resources\Eats;

use FLAIRUK\Uber\Resources\Resource;
use FLAIRUK\Uber\Response;

/**
 * Integration activation (pos_data): link your app to a merchant's store.
 *
 * 1. The merchant authorizes your app: Uber::oauth()->authorizeUrl(['eats.pos_provisioning']).
 * 2. With their token, list their stores: Uber::withToken($token)->eats()->stores()->list().
 * 3. With their token, activate(): Uber sends store.provisioned and you use app tokens from then on.
 *
 * @see https://developer.uber.com/docs/eats/guides/pos-provision
 */
class Integrations extends Resource
{
    protected array $scopes = ['eats.store'];

    protected string $api = 'eats';

    /**
     * Activate your app for a store. Needs the merchant's token (withToken()).
     *
     * @param  array<string, mixed>  $configuration  ['is_order_manager', 'integrator_store_id', 'integrator_brand_id', 'merchant_store_id', 'store_configuration_data', 'require_manual_acceptance', 'webhooks_config', 'allowed_customer_requests']
     */
    public function activate(string $storeId, array $configuration = []): Response
    {
        return $this->post($this->path($storeId), $configuration, ['eats.pos_provisioning']);
    }

    public function find(string $storeId): Response
    {
        return $this->get($this->path($storeId));
    }

    /**
     * e.g. ['integration_enabled' => false] to pause, ['is_order_manager' => false] to resign as order manager.
     *
     * @param  array<string, mixed>  $changes
     */
    public function update(string $storeId, array $changes): Response
    {
        return $this->patch($this->path($storeId), $changes);
    }

    /**
     * Remove your app from the store entirely; the merchant must set it up again.
     */
    public function remove(string $storeId): Response
    {
        return $this->delete($this->path($storeId), scopes: ['eats.pos_provisioning']);
    }

    protected function path(string $storeId): string
    {
        return 'v1/eats/stores/'.$this->segment($storeId).'/pos_data';
    }
}
