<?php

namespace FLAIRUK\Uber\Resources\Business;

use FLAIRUK\Uber\Resources\Resource;

/**
 * Uber for Business: receipts, vouchers, organisations, employees, employee
 * trips and statements. Guest and Health rides have their own entry points.
 *
 * App tokens. Third-party apps acting for an organisation that consented through
 * business.uber.com/authorize send its UUID: set UBER_ORGANIZATION_ID or pass it.
 *
 * @see https://developer.uber.com/docs/businesses/receipts/introduction
 */
class Business extends Resource
{
    protected string $api = 'business';

    public function receipts(?string $organizationId = null): Receipts
    {
        return new Receipts($this->client, $organizationId ?? $this->client->config('business.organization_id'));
    }

    public function vouchers(?string $organizationId = null): Vouchers
    {
        return new Vouchers($this->client, $organizationId ?? $this->client->config('business.organization_id'));
    }

    public function organizations(): Organizations
    {
        return new Organizations($this->client);
    }

    public function employees(?string $organizationId = null): Employees
    {
        return new Employees($this->client, $organizationId ?? $this->client->config('business.organization_id'));
    }

    public function employeeTrips(?string $organizationId = null): EmployeeTrips
    {
        return new EmployeeTrips($this->client, $organizationId ?? $this->client->config('business.organization_id'));
    }

    public function statements(?string $organizationId = null): Statements
    {
        return new Statements($this->client, $organizationId ?? $this->client->config('business.organization_id'));
    }

    /**
     * The URL an organisation admin visits to let your third-party app act for their
     * organisation. Uber redirects back with ?org_uuid=...
     *
     * @param  list<string>  $scopes
     */
    public function consentUrl(array $scopes, string $redirectUri, string $appName): string
    {
        return 'https://business.uber.com/authorize?'.http_build_query([
            'client_id' => $this->client->config('client_id'),
            'scope' => implode(' ', $scopes),
            'redirect_uri' => $redirectUri,
            'app_name' => $appName,
        ], '', '&', PHP_QUERY_RFC3986);
    }
}
