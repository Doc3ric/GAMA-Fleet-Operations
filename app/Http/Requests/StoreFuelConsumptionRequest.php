<?php

namespace App\Http\Requests;

use App\Models\Vehicle;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreFuelConsumptionRequest extends FormRequest
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
        return [
            'test_date' => ['required', 'date'],
            'vehicle_id' => [
                'required',
                function ($attribute, $value, $fail) {
                    if ($value !== 'specify' && ! Vehicle::where('id', $value)->exists()) {
                        $fail('The selected vehicle / equipment is invalid.');
                    }
                },
            ],
            'custom_equipment_code' => ['required_if:vehicle_id,specify', 'nullable', 'string', 'max:50'],
            'custom_plate_number' => ['nullable', 'string', 'max:50'],
            'custom_model' => ['nullable', 'string', 'max:100'],
            'driver_id' => ['nullable', 'exists:users,id'],
            'driver_name' => ['nullable', 'string', 'max:100'],
            'start_odometer' => ['required', 'numeric', 'min:0', 'max:9999999.99'],
            'end_odometer' => ['required', 'numeric', 'gte:start_odometer', 'max:9999999.99'],
            'fuel_consumed_liters' => ['required', 'numeric', 'gt:0', 'max:99999.999'],
            'test_route' => ['nullable', 'string', 'max:255'],
            'remarks' => ['nullable', 'string', 'max:1000'],
            'attested_by' => ['nullable', 'string', 'max:100'],
            'requested_by' => ['nullable', 'string', 'max:100'],
            'start_odometer_image' => ['nullable', 'image', 'max:10240'],
            'end_odometer_image' => ['nullable', 'image', 'max:10240'],
            'fuel_receipt_image' => ['nullable', 'image', 'max:10240'],
        ];
    }

    /**
     * Custom messages for validation rules.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'end_odometer.gte' => 'End Odometer must not be lower than Start Odometer.',
            'fuel_consumed_liters.gt' => 'Fuel Consumed (2nd Full Tank) must be greater than zero.',
            'fuel_consumed_liters.required' => 'Fuel Consumed (2nd Full Tank) is required.',
            'custom_equipment_code.required_if' => 'Please enter the Vehicle / Equipment Code when "PLEASE SPECIFY" is selected.',
        ];
    }
}
