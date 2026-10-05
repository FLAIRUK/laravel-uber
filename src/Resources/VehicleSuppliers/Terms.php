<?php

namespace FLAIRUK\Uber\Resources\VehicleSuppliers;

use FLAIRUK\Uber\Resources\Resource;
use FLAIRUK\Uber\Response;

/**
 * Rental terms deducted from driver earnings (scope vehicle_suppliers.terms.management).
 */
class Terms extends Resource
{
    protected array $scopes = ['vehicle_suppliers.terms.management'];

    /**
     * @param  array<string, mixed>  $terms
     */
    public function create(array $terms): Response
    {
        return $this->post('v1/vehicle-supplier/terms', $terms);
    }

    /**
     * @param  array<string, mixed>  $details
     */
    public function terminate(string $termsId, array $details = []): Response
    {
        return $this->patch('v1/vehicle-supplier/terms/'.$this->segment($termsId).'/terminate', $details);
    }

    /**
     * Also needs vehicle_suppliers.terms.adjustment.
     *
     * @param  array<string, mixed>  $adjustment
     */
    public function adjustBalance(string $termsId, array $adjustment): Response
    {
        return $this->post(
            'v1/vehicle-supplier/terms/'.$this->segment($termsId).'/adjust-balance',
            $adjustment,
            ['vehicle_suppliers.terms.management', 'vehicle_suppliers.terms.adjustment'],
        );
    }
}
