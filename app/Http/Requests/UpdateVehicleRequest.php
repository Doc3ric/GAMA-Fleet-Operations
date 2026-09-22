<?php

namespace App\Http\Requests;

use App\Models\Vehicle;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateVehicleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var Vehicle $vehicle */
        $vehicle = $this->route('vehicle');

        return [
            'equipment_code' => ['required', 'string', 'max:20', Rule::unique('vehicles', 'equipment_code')->ignore($vehicle->id)],
            'vehicle_type_id' => ['nullable', 'exists:vehicle_types,id'],
            'model' => ['nullable', 'string', 'max:100'],
            'plate_number' => ['nullable', 'string', 'max:30'],
            'date_acquired' => ['nullable', 'date'],
            'fuel_min' => ['nullable', 'numeric', 'min:0', 'max:9999.99'],
            'fuel_max' => ['nullable', 'numeric', 'min:0', 'max:9999.99', 'gte:fuel_min'],
            'fuel_unit' => ['nullable', 'string', 'max:20'],
            'status_value' => ['nullable', 'numeric', 'min:0', 'max:999.99'],
            'status_label' => ['nullable', 'string', 'max:50'],
            'location' => ['nullable', 'string', 'max:150'],
            'project_code' => ['nullable', 'string', 'max:50'],
            'operator_driver' => ['nullable', 'string', 'max:100'],
            'helper' => ['nullable', 'string', 'max:100'],
            'gps_status' => ['required', Rule::in(Vehicle::GPS_STATUSES)],
            'image' => ['nullable', 'image', 'max:5120'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
