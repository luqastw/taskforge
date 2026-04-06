<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('task'));
    }

    public function rules(): array
    {
        return [
            'project_column_id' => ['sometimes', 'exists:project_columns,id'],
            'parent_id' => ['nullable', 'exists:tasks,id'],
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'priority' => ['sometimes', 'in:low,medium,high,urgent'],
            'deadline' => ['nullable', 'date'],
            'order' => ['nullable', 'integer'],
        ];
    }
}
