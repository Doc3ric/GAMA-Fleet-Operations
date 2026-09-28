<?php

namespace App\Http\Requests;

use App\Models\WorkTask;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateWorkTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var WorkTask $task */
        $task = $this->route('work_task');

        return $this->user() && ($this->user()->isAdmin() || $this->user()->id === $task->user_id);
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
