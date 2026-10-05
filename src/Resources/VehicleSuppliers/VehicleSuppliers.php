<?php

namespace FLAIRUK\Uber\Resources\VehicleSuppliers;

use FLAIRUK\Uber\Resources\Resource;
use FLAIRUK\Uber\Response;

/**
 * Uber for Suppliers: fleets and vehicle suppliers managing vehicles, drivers,
 * rental terms, financing, shifts and performance data. Partner-only.
 *
 * App tokens with a scope per endpoint, except those that act for one driver with
 * their consent (marked "driver token"): Uber::withToken($driverToken)->vehicleSuppliers().
 * Money is amountE5 (the amount × 100,000). Paths mix "vehicle-suppliers" and
 * "vehicle-supplier"; both are sent exactly as Uber documents them.
 *
 * @see https://developer.uber.com/docs/vehicles/introduction
 */
class VehicleSuppliers extends Resource
{
    public const REPORTS_SCOPE = 'solutions.suppliers.reports';

    // Organisations and drivers

    public function organizations(): Response
    {
        return $this->get('v1/vehicle-suppliers/orgs', scopes: ['vehicle_suppliers.organizations.read']);
    }

    /**
     * @param  array<string, mixed>  $criteria  ['email' => ...] or ['phone_number' => ...]
     */
    public function searchDrivers(array $criteria): Response
    {
        return $this->post('v1/vehicle-suppliers/drivers/search', ['criteria' => $criteria], ['vehicle_suppliers.drivers.read'], retry: true);
    }

    /**
     * Drivers in an organisation with their status.
     *
     * @param  array<string, mixed>  $query  ['org_id', 'page_size', 'page_token', ...]
     */
    public function drivers(array $query): Response
    {
        return $this->get('v1/vehicle-suppliers/drivers', $query, ['solutions.suppliers.drivers.status.read']);
    }

    /**
     * @param  array<string, mixed>  $query
     */
    public function driverActions(array $query): Response
    {
        return $this->get('v1/vehicle-suppliers/drivers/actions', $query, ['solutions.suppliers.drivers.status.read']);
    }

    /**
     * @param  array<string, mixed>  $query
     */
    public function liveLocations(array $query): Response
    {
        return $this->post('v1/vehicle-suppliers/drivers/live-location', $query, ['supplier.fleet.drivers.live_location.read'], retry: true);
    }

    /**
     * @param  array<string, mixed>  $query
     */
    public function driverTimeline(array $query): Response
    {
        return $this->post('v1/vehicle-suppliers/driver/timeline-info', $query, ['supplier.driver.activity.read'], retry: true);
    }

    /**
     * The consenting driver's profile (driver token, scope partner.accounts).
     */
    public function partner(): Response
    {
        return $this->get('v1/partners/me', scopes: []);
    }

    /**
     * The consenting driver's compliance (driver token).
     */
    public function partnerCompliance(): Response
    {
        return $this->get('v1/vehicle-suppliers/partners/me/compliance', scopes: []);
    }

    /**
     * The consenting driver's risk profile (driver token).
     *
     * @param  list<string>  $riskModels
     */
    public function partnerRiskProfile(array $riskModels = []): Response
    {
        return $this->get('v3/vehicle-suppliers/partners/me/risk-profile', ['risk_models' => $riskModels ? implode(',', $riskModels) : null], []);
    }

    /**
     * Block or unblock cash trips for a driver.
     *
     * @param  array<string, mixed>  $action
     */
    public function cashBlock(array $action): Response
    {
        return $this->post('v1/vehicle-suppliers/cash-block-action', $action, ['vehicle_suppliers.cash_block.write']);
    }

    /**
     * @param  array<string, mixed>  $query
     */
    public function cashBlockActions(array $query): Response
    {
        return $this->post('v1/vehicle-suppliers/query-cash-block-actions', $query, ['vehicle_suppliers.cash_block.read'], retry: true);
    }

    // Vehicles

    public function vehicles(): Vehicles
    {
        return new Vehicles($this->client);
    }

    // Money

    /**
     * Earner payments over the last 24 hours.
     *
     * @param  array<string, mixed>  $query
     */
    public function earnerPayments(array $query = []): Response
    {
        return $this->get('v1/vehicle-suppliers/earners/payments', $query, ['supplier.partner.payments']);
    }

    /**
     * Payment transactions. Limited to one request a second.
     *
     * @param  array<string, mixed>  $query
     */
    public function transactions(string $organizationId, array $query = []): Response
    {
        return $this->send('POST', 'v1/vehicle-suppliers/transactions', [
            'query' => ['org_id' => $organizationId],
            'json' => $query,
        ], ['supplier.partner.payments'], retry: true);
    }

    // Performance data and reports

    /**
     * @param  array<string, mixed>  $query  ['reportRequests' => [...]]
     */
    public function analytics(array $query): Response
    {
        return $this->post('v1/vehicle-suppliers/analytics-data/query', $query, ['solutions.suppliers.metrics.read'], retry: true);
    }

    /**
     * @param  array<string, mixed>  $query
     */
    public function vehicleMetrics(array $query = []): Response
    {
        return $this->get('v2/vehicle-suppliers/vehicles', $query, ['solutions.suppliers.metrics.read']);
    }

    /**
     * Start an offline report (at most six running per organisation).
     *
     * @param  array<string, mixed>  $report  ['reportType' => 'REPORT_TYPE_...', ...]
     */
    public function createReport(string $organizationId, array $report): Response
    {
        return $this->post($this->reportsPath($organizationId), $report, [self::REPORTS_SCOPE]);
    }

    /**
     * @param  array<string, mixed>  $query
     */
    public function reports(string $organizationId, array $query = []): Response
    {
        return $this->get($this->reportsPath($organizationId), $query, [self::REPORTS_SCOPE]);
    }

    public function report(string $organizationId, string $reportId): Response
    {
        return $this->get($this->reportsPath($organizationId).'/'.$this->segment($reportId), scopes: [self::REPORTS_SCOPE]);
    }

    /**
     * A signed CSV download link, valid for 60 seconds.
     */
    public function reportLink(string $organizationId, string $reportId): Response
    {
        return $this->post($this->reportsPath($organizationId).'/'.$this->segment($reportId).'/link', [], [self::REPORTS_SCOPE], retry: true);
    }

    // Rental terms, financing and shifts

    public function terms(): Terms
    {
        return new Terms($this->client);
    }

    public function financing(): Financing
    {
        return new Financing($this->client);
    }

    public function shifts(): Shifts
    {
        return new Shifts($this->client);
    }

    protected function reportsPath(string $organizationId): string
    {
        return 'v1/vehicle-suppliers/suppliers/'.$this->segment($organizationId).'/reports';
    }
}
