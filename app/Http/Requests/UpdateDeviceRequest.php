<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateDeviceRequest extends FormRequest
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
            'device_name' => ['required', 'string', 'max:100'],
            'imei' => [
                'nullable',
                'string',
                'max:50',
                Rule::unique('devices', 'imei')->ignore($this->route('device')),
            ],
            'model' => ['nullable', 'string', 'max:50'],
            'activated_date' => ['nullable', 'date'],
            'sales_time' => ['nullable', 'date'],
            'sim' => ['nullable', 'string', 'max:50'],
            'expiration_date' => ['nullable', 'date'],
            'group_name' => ['nullable', 'string', 'max:100'],
            'iccid' => ['nullable', 'string', 'max:50'],
            'imsi' => ['nullable', 'string', 'max:50'],
            'mileage' => ['nullable', 'numeric', 'min:0', 'max:9999999.99'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
