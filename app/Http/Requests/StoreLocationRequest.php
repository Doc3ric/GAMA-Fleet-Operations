<?php

namespace App\Http\Requests;

use App\Models\Location;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreLocationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() && ($this->user()->isAdmin() || $this->user()->isOperator());
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'code' => [
                'required',
                'string',
                'max:50',
                Rule::unique('locations', 'code')->where(function ($query) {
                    return $query->where('status', Location::STATUS_ACTIVE);
                }),
            ],
            'official_name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'string', Rule::in(Location::TYPES)],
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'address' => ['required', 'string'],
            'barangay' => ['nullable', 'string', 'max:100'],
            'municipality' => ['nullable', 'string', 'max:100'],
            'province' => ['nullable', 'string', 'max:100'],
            'status' => ['required', 'string', Rule::in(Location::STATUSES)],
            'notes' => ['nullable', 'string'],
            'image' => ['nullable', 'image', 'max:5120'],
            'aliases' => ['nullable', 'array'],
            'aliases.*' => ['nullable', 'string', 'max:255'],
            'area_consultant' => ['nullable', 'string', 'max:255'],
            'contact_number' => ['nullable', 'string', 'max:30'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'code.unique' => 'An active location with this primary code already exists. Please choose a unique code.',
            'latitude.between' => 'Latitude must be a valid coordinate between -90.0000000 and 90.0000000.',
            'longitude.between' => 'Longitude must be a valid coordinate between -180.0000000 and 180.0000000.',
        ];
    }
}
