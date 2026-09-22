<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class StartTripRequest extends FormRequest
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
            'client_id' => ['required', 'uuid'],
            'vehicle_id' => ['required', 'integer', 'exists:vehicles,id'],
            'trip_date' => ['nullable', 'date'],
            'time_in' => ['required', 'string'],
            'origin_latitude' => ['required', 'numeric', 'between:-90,90'],
            'origin_longitude' => ['required', 'numeric', 'between:-180,180'],
            'origin_accuracy' => ['nullable', 'numeric', 'min:0'],
            'origin_address' => ['nullable', 'string'],
            'remarks' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
