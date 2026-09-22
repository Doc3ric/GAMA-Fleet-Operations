<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'report_date' => [
                'required',
                'date',
                Rule::unique('reports')->where(fn ($q) => $q
                    ->where('report_type', 'long_idling')
                    ->where('created_by', auth()->id())
                ),
            ],
            'remarks' => 'nullable|string|max:1000',
            'duplicate_from' => 'nullable|exists:reports,id',
        ];
    }

    public function messages(): array
    {
        return [
            'report_date.unique' => 'A Long Idling report already exists for this date.',
        ];
    }
}
