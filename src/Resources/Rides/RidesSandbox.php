<?php

namespace FLAIRUK\Uber\Resources\Rides;

use FLAIRUK\Uber\Exceptions\ConfigurationException;
use FLAIRUK\Uber\Resources\Resource;
use FLAIRUK\Uber\Response;
use FLAIRUK\Uber\Uber;

/**
 * Drive sandbox rides through their lifecycle and simulate surge.
 *
 * @see https://developer.uber.com/docs/riders/guides/sandbox
 */
class RidesSandbox extends Resource
{
    /**
     * In order: processing, accepted, arriving, in_progress, completed. driver_canceled at any point.
     */
    public const STATUSES = ['processing', 'no_drivers_available', 'accepted', 'arriving', 'in_progress', 'driver_canceled', 'rider_canceled', 'completed'];

    public function __construct(Uber $client)
    {
        if (! $client->isSandbox()) {
            throw new ConfigurationException('Uber sandbox controls need the sandbox: use Uber::sandbox() or set UBER_SANDBOX=true.');
        }

        parent::__construct($client);
    }

    /**
     * Move a sandbox ride to the next status.
     */
    public function setStatus(string $requestId, string $status): Response
    {
        return $this->put('v1.2/sandbox/requests/'.$this->segment($requestId), ['status' => $status]);
    }

    /**
     * Simulate surge (2.0 or more needs confirmation) or no drivers for a product.
     */
    public function setProduct(string $productId, ?float $surgeMultiplier = null, ?bool $driversAvailable = null): Response
    {
        return $this->put('v1.2/sandbox/products/'.$this->segment($productId), [
            'surge_multiplier' => $surgeMultiplier,
            'drivers_available' => $driversAvailable,
        ]);
    }
}
