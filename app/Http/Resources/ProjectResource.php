<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProjectResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'workspace_id' => $this->workspace_id,
            'name' => $this->name,
            'description' => $this->description,
            'status' => $this->status,
            'deadline' => $this->deadline?->toIso8601String(),
            'workspace' => new WorkspaceResource($this->whenLoaded('workspace')),
            'columns' => ProjectColumnResource::collection($this->whenLoaded('columns')),
            'tasks_count' => $this->when($this->relationLoaded('tasks'), fn () => $this->tasks->count()),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
