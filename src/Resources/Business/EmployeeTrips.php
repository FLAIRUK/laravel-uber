<?php

namespace FLAIRUK\Uber\Resources\Business;

use FLAIRUK\Uber\Exceptions\ConfigurationException;
use FLAIRUK\Uber\Response;

/**
 * Rides for an organisation's employees, billed to their business profile
 * (scope business.trips, privileged).
 *
 * @see https://developer.uber.com/docs/employees-trips/introduction
 */
class EmployeeTrips extends OrganizationResource
{
    protected array $scopes = ['business.trips'];

    protected ?string $sandboxRunId = null;

    public function inSandboxRun(string $runId): static
    {
        $clone = clone $this;
        $clone->sandboxRunId = $runId;

        return $clone;
    }

    /**
     * The programmes and expense codes an employee can ride under.
     */
    public function programs(string $email): Response
    {
        return $this->post('v2/employees/programs', [
            'organization_uuid' => $this->organizationId,
            'employee' => ['email' => $email],
        ], retry: true);
    }

    /**
     * @param  array<string, mixed>  $route  ['pickup', 'dropoff', 'scheduling'?, ...]
     */
    public function estimates(array $route): Response
    {
        return $this->post('v2/employees/trips/estimates', $route, retry: true)->paginate('product_estimates');
    }

    /**
     * @param  array<string, mixed>  $options  ['address', 'place_id', ...]
     */
    public function zones(float $latitude, float $longitude, array $options = []): Response
    {
        return $this->get('v2/employees/zones', ['latitude' => $latitude, 'longitude' => $longitude] + $options);
    }

    public function autocomplete(string $query, ?float $latitude = null, ?float $longitude = null): Response
    {
        return $this->get('v2/employees/address/autocomplete', ['query' => $query, 'latitude' => $latitude, 'longitude' => $longitude]);
    }

    /**
     * Never retried automatically. A ConflictException (surge, fare_expired) means estimate again.
     *
     * @param  array<string, mixed>  $trip  ['customer' => ['email'], 'product_id', 'fare_id', 'pickup', 'dropoff', 'expense_code'?, 'program_uuid'?, ...]
     */
    public function create(array $trip): Response
    {
        return $this->post('v2/employees/trips', $trip);
    }

    public function find(string $requestId, bool $includeEditableFields = false): Response
    {
        return $this->get('v2/employees/trips/'.$this->segment($requestId), ['include_editable_fields' => $includeEditableFields ?: null]);
    }

    /**
     * @param  array<string, mixed>  $changes
     */
    public function update(string $requestId, array $changes): Response
    {
        return $this->send('PUT', 'v2/employees/trips', [
            'query' => ['trip_uuid' => $requestId],
            'json' => ['request_id' => $requestId] + $changes,
        ], retry: false);
    }

    public function cancel(string $requestId): Response
    {
        return $this->delete('v2/employees/trips/'.$this->segment($requestId));
    }

    /**
     * @param  array<string, mixed>  $search  ['search_filters' => ['user_identifiers', 'trip_statuses', 'interval'], 'paging_option'?]
     */
    public function search(array $search): Response
    {
        return $this->post('v1/trips/search', $search, retry: true);
    }

    /**
     * @param  array<string, mixed>  $run
     */
    public function createSandboxRun(array $run): Response
    {
        $this->assertSandbox();

        return $this->post('v2/employees/sandbox/run', $run);
    }

    public function sandboxRun(string $runId): Response
    {
        $this->assertSandbox();

        return $this->get('v2/employees/sandbox/run/'.$this->segment($runId));
    }

    public function sandboxDriverState(string $runId, string $driverId, string $state): Response
    {
        $this->assertSandbox();

        return $this->post('v2/employees/sandbox/driver-state', ['run_id' => $runId, 'driver_id' => $driverId, 'driver_state' => $state]);
    }

    protected function assertSandbox(): void
    {
        if (! $this->client->isSandbox()) {
            throw new ConfigurationException('Uber sandbox runs need the sandbox: use Uber::sandbox() or set UBER_SANDBOX=true.');
        }
    }

    protected function headers(): array
    {
        return parent::headers() + ['x-uber-sandbox-runuuid' => $this->sandboxRunId];
    }
}
