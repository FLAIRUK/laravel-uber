<?php

namespace FLAIRUK\Uber\Resources\Business;

use FLAIRUK\Uber\Resources\Resource;
use FLAIRUK\Uber\Response;

/**
 * Uber for Business organisations: create and look up customer organisations
 * (scope business.organizations, privileged).
 *
 * @see https://developer.uber.com/docs/organizations/introduction
 */
class Organizations extends Resource
{
    protected array $scopes = ['business.organizations'];

    protected string $api = 'business';

    /**
     * @param  array<string, mixed>  $organization  ['organization' => ['name', 'home_country_iso', 'billing_mode'], 'admin' => [...], 'consents_accepted' => true, 'u4b_products' => [...]]
     */
    public function create(array $organization): Response
    {
        return $this->post('v1/organizations', $organization);
    }

    public function find(string $organizationId): Response
    {
        return $this->get('v1/organizations/'.$this->segment($organizationId));
    }

    public function remove(string $organizationId): Response
    {
        return $this->delete('v1/organizations/'.$this->segment($organizationId));
    }

    /**
     * @param  string|null  $category  RIDES, EATS, GUESTS, VOUCHERS or HEALTH
     */
    public function programs(string $organizationId, ?string $name = null, ?string $category = null): Response
    {
        return $this->get('v1/organizations/'.$this->segment($organizationId).'/programs', [
            'program_name' => $name,
            'program_category' => $category,
        ]);
    }
}
