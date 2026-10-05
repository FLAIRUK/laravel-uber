<?php

namespace FLAIRUK\Uber\Resources\GuestRides;

use FLAIRUK\Uber\Exceptions\ConfigurationException;
use FLAIRUK\Uber\Resources\Resource;
use FLAIRUK\Uber\Response;
use FLAIRUK\Uber\Uber;

/**
 * Sandbox runs: a set of test riders and drivers that lasts eight hours.
 * Pass the run id to inSandboxRun() on Guest Rides or Health, then drive trips
 * with driverState().
 *
 * @see https://developer.uber.com/docs/guest-rides/guides/sandbox
 */
class ManagedRidesSandbox extends Resource
{
    public const DRIVER_STATES = ['GO_ONLINE', 'GO_OFFLINE', 'ACCEPT', 'ARRIVED', 'BEGIN_TRIP', 'DROPOFF', 'CANCEL'];

    protected string $api = 'guests';

    /**
     * @param  list<string>  $scopes
     */
    public function __construct(Uber $client, protected string $prefix = 'guests', array $scopes = ['guests.trips'], protected ?string $organizationId = null)
    {
        $this->scopes = $scopes;

        if (! $client->isSandbox()) {
            throw new ConfigurationException('Uber sandbox controls need the sandbox: use Uber::sandbox() or set UBER_SANDBOX=true.');
        }

        parent::__construct($client);
    }

    /**
     * Start a run. Allow up to a minute before using it.
     *
     * @param  array<string, mixed>  $run  ['driver_locations' => [...], 'pickup_location' => [...], 'dropoff_location' => [...], 'parent_product_type_id' => ..., 'preferences'?]
     */
    public function createRun(array $run = []): Response
    {
        return $this->post('v1/'.$this->prefix.'/sandbox/run', $run);
    }

    /**
     * A run and its test driver ids.
     */
    public function run(string $runId): Response
    {
        return $this->get('v1/'.$this->prefix.'/sandbox/run/'.$this->segment($runId));
    }

    /**
     * Move a test driver: GO_ONLINE, ACCEPT, ARRIVED, BEGIN_TRIP, DROPOFF, CANCEL or GO_OFFLINE.
     */
    public function driverState(string $runId, string $driverId, string $state): Response
    {
        return $this->post('v1/'.$this->prefix.'/sandbox/driver-state', [
            'run_id' => $runId,
            'driver_id' => $driverId,
            'driver_state' => $state,
        ]);
    }

    protected function headers(): array
    {
        return ['x-uber-organizationuuid' => $this->organizationId ?? $this->client->config('business.organization_id')];
    }
}
