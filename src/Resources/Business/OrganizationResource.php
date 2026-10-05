<?php

namespace FLAIRUK\Uber\Resources\Business;

use FLAIRUK\Uber\Exceptions\ConfigurationException;
use FLAIRUK\Uber\Resources\Resource;
use FLAIRUK\Uber\Uber;

/**
 * A Business resource bound to one organisation.
 */
abstract class OrganizationResource extends Resource
{
    protected string $api = 'business';

    public function __construct(Uber $client, protected ?string $organizationId = null)
    {
        parent::__construct($client);
    }

    protected function organizationId(): string
    {
        return filled($this->organizationId)
            ? (string) $this->organizationId
            : throw new ConfigurationException('No Uber for Business organisation: set UBER_ORGANIZATION_ID or pass the organisation id.');
    }

    protected function headers(): array
    {
        return ['x-uber-organizationuuid' => $this->organizationId];
    }
}
