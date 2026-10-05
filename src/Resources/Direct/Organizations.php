<?php

namespace FLAIRUK\Uber\Resources\Direct;

use FLAIRUK\Uber\Resources\Resource;
use FLAIRUK\Uber\Response;

/**
 * Direct Organizations API: create a sub-organisation per merchant or store
 * under your root organisation (your customer id), and invite its members.
 *
 * @see https://developer.uber.com/docs/deliveries/guides/organizations
 */
class Organizations extends Resource
{
    protected array $scopes = ['direct.organizations'];

    protected string $api = 'direct';

    /**
     * @param  array<string, mixed>  $organization  ['info' => ['name', 'billing_type' => 'BILLING_TYPE_CENTRALIZED', ...], 'hierarchy_info' => ['parent_organization_id' => ...], 'options'?]
     */
    public function create(array $organization): Response
    {
        return $this->post('v1/direct/organizations', $organization);
    }

    public function find(string $organizationId): Response
    {
        return $this->get('v1/direct/organizations/'.$this->segment($organizationId));
    }

    /**
     * @param  array<string, mixed>  $invitation  ['user_details' => ['email', 'first_name', 'last_name'], 'roles' => ['ROLE_ADMIN'|'ROLE_EMPLOYEE'|'ROLE_SUPPORT'], 'role_assignments'?]
     */
    public function invite(string $organizationId, array $invitation): Response
    {
        return $this->post('v1/direct/organizations/'.$this->segment($organizationId).'/memberships/invite', $invitation);
    }
}
