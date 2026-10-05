<?php

namespace FLAIRUK\Uber\Resources\VehicleSuppliers;

use DateTimeInterface;
use FLAIRUK\Uber\Resources\Resource;
use FLAIRUK\Uber\Response;

/**
 * Driver shifts (scope vehicle_suppliers.shifts.management, one request a second).
 * Times are UTC milliseconds; DateTimeInterface values are converted for you.
 */
class Shifts extends Resource
{
    protected array $scopes = ['vehicle_suppliers.shifts.management'];

    public function forEarner(string $earnerId, DateTimeInterface|int $start, DateTimeInterface|int $end): Response
    {
        return $this->get('v2/vehicle-supplier/shifts/earner/'.$this->segment($earnerId), [
            'start_time_utc' => $this->milliseconds($start),
            'end_time_utc' => $this->milliseconds($end),
        ]);
    }

    /**
     * @param  string  $marketplace  MARKETPLACE_TYPE_RIDES or MARKETPLACE_TYPE_EATS
     * @param  array<string, mixed>  $metadata
     */
    public function save(string $earnerId, DateTimeInterface|int $start, DateTimeInterface|int $end, ?string $zoneId = null, string $marketplace = 'MARKETPLACE_TYPE_RIDES', array $metadata = []): Response
    {
        return $this->post('v2/vehicle-supplier/shifts/earner/'.$this->segment($earnerId), ['shift' => array_filter([
            'start_time_utc' => ['value' => $this->milliseconds($start)],
            'end_time_utc' => ['value' => $this->milliseconds($end)],
            'zone_id' => $zoneId,
            'marketplace_type' => $marketplace,
            'metadata' => $metadata ?: null,
        ], fn ($value) => $value !== null)]);
    }

    public function remove(string $earnerId, DateTimeInterface|int $start, DateTimeInterface|int $end): Response
    {
        return $this->post('v2/vehicle-supplier/shifts/delete/earner/'.$this->segment($earnerId), [
            'start_time_utc' => ['value' => $this->milliseconds($start)],
            'end_time_utc' => ['value' => $this->milliseconds($end)],
        ]);
    }

    /**
     * Version 1 shifts, by driver id.
     */
    public function forDriver(string $driverId, DateTimeInterface|int $start, DateTimeInterface|int $end): Response
    {
        return $this->get('v1/vehicle-supplier/shifts/driver/'.$this->segment($driverId), [
            'start_time_utc' => $this->milliseconds($start),
            'end_time_utc' => $this->milliseconds($end),
        ]);
    }

    /**
     * @param  array<string, mixed>  $shift
     */
    public function saveForDriver(string $driverId, array $shift): Response
    {
        return $this->post('v1/vehicle-supplier/shifts/driver/'.$this->segment($driverId), $shift);
    }

    protected function milliseconds(DateTimeInterface|int $time): int
    {
        return $time instanceof DateTimeInterface ? (int) $time->format('Uv') : $time;
    }
}
