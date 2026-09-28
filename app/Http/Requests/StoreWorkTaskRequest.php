<?php

namespace App\Http\Requests;

use App\Models\WorkTask;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreWorkTaskRequest extends FormRequest
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
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'category' => ['nullable', 'string', 'max:100'],
            'status' => ['required', 'string', Rule::in(WorkTask::VALID_STATUSES)],
            'priority' => ['required', 'string', Rule::in(WorkTask::VALID_PRIORITIES)],
            'due_date' => ['nullable', 'date'],
            'next_action' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
