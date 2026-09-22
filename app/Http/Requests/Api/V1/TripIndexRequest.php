<?php

namespace App\Http\Requests\Api\V1;

use App\Models\DriverTrip;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TripIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'date' => ['nullable', 'date'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'status' => ['nullable', 'string', Rule::in(DriverTrip::VALID_STATUSES)],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
