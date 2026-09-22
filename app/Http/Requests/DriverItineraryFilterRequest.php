<?php

namespace App\Http\Requests;

use App\Models\DriverTrip;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DriverItineraryFilterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', DriverTrip::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:100'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'driver_id' => ['nullable', 'integer', 'exists:users,id'],
            'vehicle_id' => ['nullable', 'integer', 'exists:vehicles,id'],
            'status' => ['nullable', 'string', Rule::in(DriverTrip::VALID_STATUSES)],
        ];
    }
}
