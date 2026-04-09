<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProjectRequest extends FormRequest
{
    /**
     * Valid status transitions.
     */
    private const STATUS_TRANSITIONS = [
        'active' => ['on_hold', 'archived'],
        'on_hold' => ['active', 'archived'],
        'archived' => ['active'],
    ];

    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('project'));
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'status' => ['sometimes', 'in:active,on_hold,archived'],
            'deadline' => ['nullable', 'date'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if ($this->has('status')) {
                $project = $this->route('project');
                $currentStatus = $project->status;
                $newStatus = $this->input('status');

                if ($currentStatus === $newStatus) {
                    return;
                }

                $allowedTransitions = self::STATUS_TRANSITIONS[$currentStatus] ?? [];

                if (! in_array($newStatus, $allowedTransitions)) {
                    $validator->errors()->add(
                        'status',
                        "Cannot transition from '{$currentStatus}' to '{$newStatus}'. Allowed: ".implode(', ', $allowedTransitions).'.'
                    );
                }
            }
        });
    }
}
