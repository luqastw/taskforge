<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Task;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Task::class);
    }

    public function rules(): array
    {
        return [
            'project_id' => [
                'required',
                Rule::exists('projects', 'id')->where('tenant_id', $this->user()->tenant_id),
            ],
            'project_column_id' => [
                'required',
                Rule::exists('project_columns', 'id')->where('project_id', $this->input('project_id')),
            ],
            'parent_id' => [
                'nullable',
                Rule::exists('tasks', 'id')->where('project_id', $this->input('project_id')),
            ],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'priority' => ['nullable', 'in:low,medium,high,urgent'],
            'deadline' => ['nullable', 'date', 'after:now'],
            'order' => ['nullable', 'integer', 'min:0'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'tenant_id' => $this->user()->tenant_id,
        ]);
    }
}
