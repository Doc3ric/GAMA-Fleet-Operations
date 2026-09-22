<?php

namespace App\Http\Resources\Api\V1;

use App\Models\DriverVehicleAssignment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read DriverVehicleAssignment $resource
 */
class VehicleAssignmentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $vehicle = $this->resource->vehicle;

        return [
            'assignment_id' => $this->resource->id,
            'vehicle_id' => $vehicle?->id,
            'equipment_code' => $vehicle?->equipment_code,
            'model' => $vehicle?->model,
            'plate_number' => $vehicle?->plate_number,
            'vehicle_type' => $vehicle?->vehicleType?->name,
            'assigned_from' => $this->resource->assigned_from?->toDateString(),
            'assigned_until' => $this->resource->assigned_until?->toDateString(),
            'notes' => $this->resource->notes,
        ];
    }
}
