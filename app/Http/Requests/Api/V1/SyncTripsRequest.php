<?php

namespace App\Http\Requests\Api\V1;

use App\Models\DriverTrip;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SyncTripsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isDriver() ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'trips' => ['required', 'array', 'min:1', 'max:100'],
            'trips.*.client_id' => ['required', 'uuid'],
            'trips.*.vehicle_id' => ['required', 'integer', 'exists:vehicles,id'],
            'trips.*.trip_date' => ['nullable', 'date'],
            'trips.*.time_in' => ['required', 'string'],
            'trips.*.origin_latitude' => ['required', 'numeric', 'between:-90,90'],
            'trips.*.origin_longitude' => ['required', 'numeric', 'between:-180,180'],
            'trips.*.origin_accuracy' => ['nullable', 'numeric', 'min:0'],
            'trips.*.origin_address' => ['nullable', 'string'],
            'trips.*.destination_latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'trips.*.destination_longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'trips.*.destination_accuracy' => ['nullable', 'numeric', 'min:0'],
            'trips.*.destination_address' => ['nullable', 'string'],
            'trips.*.time_out' => ['nullable', 'string'],
            'trips.*.status' => ['nullable', 'string', Rule::in(DriverTrip::VALID_STATUSES)],
            'trips.*.remarks' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
