<?php

namespace FLAIRUK\Uber\Resources\VehicleSuppliers;

use FLAIRUK\Uber\Resources\Resource;
use FLAIRUK\Uber\Response;

/**
 * Vehicle financing contracts (scope vehicle_suppliers.financing.contracts).
 */
class Financing extends Resource
{
    protected array $scopes = ['vehicle_suppliers.financing.contracts'];

    /**
     * @param  array<string, mixed>  $contract
     */
    public function create(array $contract): Response
    {
        return $this->post('v1/vehicle-supplier/financing/contracts', $contract);
    }

    public function find(string $contractId): Response
    {
        return $this->get('v1/vehicle-supplier/financing/contracts/'.$this->segment($contractId));
    }

    /**
     * @param  array<string, mixed>  $criteria
     */
    public function search(array $criteria): Response
    {
        return $this->post('v1/vehicle-supplier/financing/contracts/search', $criteria, retry: true);
    }

    /**
     * @param  array<string, mixed>  $changes  including the contract id
     */
    public function update(array $changes): Response
    {
        return $this->patch('v1/vehicle-supplier/financing/contracts/update', $changes);
    }

    /**
     * @param  array<string, mixed>  $details
     */
    public function terminate(string $contractId, array $details = []): Response
    {
        return $this->patch('v1/vehicle-supplier/financing/contracts/terminate/'.$this->segment($contractId), $details);
    }

    /**
     * @param  array<string, mixed>  $adjustment  including the contract id
     */
    public function adjust(array $adjustment): Response
    {
        return $this->patch('v1/vehicle-supplier/financing/contracts/adjust', $adjustment);
    }
}
