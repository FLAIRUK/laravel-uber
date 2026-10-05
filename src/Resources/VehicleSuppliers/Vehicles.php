<?php

namespace FLAIRUK\Uber\Resources\VehicleSuppliers;

use FLAIRUK\Uber\Resources\Resource;
use FLAIRUK\Uber\Response;

/**
 * Vehicles: create, update, search, transfer and assign to drivers.
 */
class Vehicles extends Resource
{
    protected array $scopes = ['vehicle_suppliers.vehicles.read'];

    /**
     * @param  array<string, mixed>  $vehicle  ['owner_id', 'make', 'model', 'year', 'vin', ...]
     */
    public function create(array $vehicle): Response
    {
        return $this->post('v1/vehicle-suppliers/vehicles', $vehicle, ['vehicle_suppliers.vehicles.write']);
    }

    /**
     * @param  array<string, mixed>  $query  ['org_id', 'page_size', 'page_token']
     */
    public function list(array $query): Response
    {
        return $this->get('v1/vehicle-suppliers/vehicles', $query);
    }

    public function find(string $vehicleId, bool $allFields = false): Response
    {
        return $this->get('v1/vehicle-suppliers/vehicles/'.$this->segment($vehicleId), ['fields' => $allFields ? '_all_' : null]);
    }

    /**
     * @param  array<string, mixed>  $changes
     */
    public function update(string $vehicleId, array $changes): Response
    {
        return $this->patch('v1/vehicle-suppliers/vehicles/'.$this->segment($vehicleId), $changes, ['vehicle_suppliers.vehicles.write']);
    }

    public function remove(string $vehicleId): Response
    {
        return $this->delete('v1/vehicle-suppliers/vehicles/'.$this->segment($vehicleId), scopes: ['vehicle_suppliers.vehicles.write']);
    }

    /**
     * @param  array<string, mixed>  $filters  ['vin' => ...] or ['license_plate' => ...]
     */
    public function search(array $filters): Response
    {
        return $this->post('v1/vehicle-suppliers/vehicles/search', ['filters' => $filters], retry: true);
    }

    public function transfer(string $vehicleId, string $newOwnerId): Response
    {
        return $this->post('v1/vehicle-suppliers/vehicles/'.$this->segment($vehicleId).'/transfer', ['new_owner_id' => $newOwnerId], ['vehicle_suppliers.vehicles.write']);
    }

    public function assign(string $vehicleId, string $driverId): Response
    {
        return $this->post('v1/vehicle-suppliers/vehicles/'.$this->segment($vehicleId).'/assign', ['driver_id' => $driverId], ['vehicle_suppliers.vehicles.assignment']);
    }

    public function unassign(string $vehicleId, string $driverId): Response
    {
        return $this->post('v1/vehicle-suppliers/vehicles/'.$this->segment($vehicleId).'/unassign', ['driver_id' => $driverId], ['vehicle_suppliers.vehicles.assignment']);
    }

    /**
     * Upload a vehicle document. Uber doesn't state a scope; the vehicles write scope is used.
     *
     * @param  string  $content  the file's raw bytes (base64-encoded for you)
     */
    public function uploadDocument(string $vehicleId, string $documentType, string $content, string $contentType, ?string $expiryDate = null): Response
    {
        return $this->post('v1/solutions/vehicles/'.$this->segment($vehicleId).'/documents', [
            'document_type' => $documentType,
            'content' => base64_encode($content),
            'content_type' => $contentType,
            'document_expiry_date' => $expiryDate,
        ], ['vehicle_suppliers.vehicles.write']);
    }
}
