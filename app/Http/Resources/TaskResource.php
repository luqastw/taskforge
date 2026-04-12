<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TaskResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'project_id' => $this->project_id,
            'project_column_id' => $this->project_column_id,
            'parent_id' => $this->parent_id,
            'title' => $this->title,
            'description' => $this->description,
            'priority' => $this->priority,
            'deadline' => $this->deadline?->toIso8601String(),
            'order' => $this->order,
            'project' => new ProjectResource($this->whenLoaded('project')),
            'column' => new ProjectColumnResource($this->whenLoaded('column')),
            'parent' => new TaskResource($this->whenLoaded('parent')),
            'subtasks' => TaskResource::collection($this->whenLoaded('subtasks')),
            'assignees' => MemberResource::collection($this->whenLoaded('assignees')),
            'tags' => TagResource::collection($this->whenLoaded('tags')),
            'subtasks_count' => $this->whenCounted('subtasks'),
            'assignees_count' => $this->whenCounted('assignees'),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
