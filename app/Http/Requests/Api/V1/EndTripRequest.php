<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class EndTripRequest extends FormRequest
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
            'time_out' => ['required', 'string'],
            'destination_latitude' => ['required', 'numeric', 'between:-90,90'],
            'destination_longitude' => ['required', 'numeric', 'between:-180,180'],
            'destination_accuracy' => ['nullable', 'numeric', 'min:0'],
            'destination_address' => ['nullable', 'string'],
            'remarks' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
