<?php

namespace App\Http\Requests;

use App\Models\WorkNote;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreWorkNoteRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'content' => ['nullable', 'string', 'max:50000'],
            'is_pinned' => ['nullable', 'boolean'],
            'color' => ['nullable', 'string', Rule::in(WorkNote::VALID_COLORS)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'title.required' => 'The subject is required.',
            'title.max' => 'The subject may not be greater than 255 characters.',
        ];
    }
}
