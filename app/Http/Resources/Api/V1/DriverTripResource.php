<?php

namespace App\Http\Resources\Api\V1;

use App\Models\DriverTrip;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read DriverTrip $resource
 */
class DriverTripResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'client_id' => $this->resource->client_id,
            'driver' => [
                'id' => $this->resource->driver?->id,
                'name' => $this->resource->driver?->name,
                'email' => $this->resource->driver?->email,
            ],
            'vehicle' => [
                'id' => $this->resource->vehicle?->id,
                'equipment_code' => $this->resource->vehicle?->equipment_code,
                'model' => $this->resource->vehicle?->model,
                'plate_number' => $this->resource->vehicle?->plate_number,
                'vehicle_type' => $this->resource->vehicle?->vehicleType?->name,
            ],
            'driver_vehicle_assignment_id' => $this->resource->driver_vehicle_assignment_id,
            'trip_date' => $this->resource->trip_date?->toDateString(),
            'time_in' => $this->resource->time_in,
            'time_out' => $this->resource->time_out,
            'origin' => [
                'latitude' => $this->resource->origin_latitude,
                'longitude' => $this->resource->origin_longitude,
                'accuracy' => $this->resource->origin_accuracy,
                'address' => $this->resource->origin_address,
            ],
            'destination' => [
                'latitude' => $this->resource->destination_latitude,
                'longitude' => $this->resource->destination_longitude,
                'accuracy' => $this->resource->destination_accuracy,
                'address' => $this->resource->destination_address,
            ],
            'status' => $this->resource->status,
            'remarks' => $this->resource->remarks,
            'created_at' => $this->resource->created_at?->toIso8601String(),
            'updated_at' => $this->resource->updated_at?->toIso8601String(),
        ];
    }
}
