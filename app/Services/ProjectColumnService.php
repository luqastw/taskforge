<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Project;
use App\Models\ProjectColumn;
use Illuminate\Database\Eloquent\Collection;

class ProjectColumnService
{
    public function createDefaultColumns(Project $project): void
    {
        $defaultColumns = [
            ['name' => 'Backlog', 'color' => '#6B7280', 'order' => 1],
            ['name' => 'To Do', 'color' => '#3B82F6', 'order' => 2],
            ['name' => 'In Progress', 'color' => '#F59E0B', 'order' => 3],
            ['name' => 'Review', 'color' => '#8B5CF6', 'order' => 4],
            ['name' => 'Done', 'color' => '#10B981', 'order' => 5],
        ];

        foreach ($defaultColumns as $column) {
            ProjectColumn::create([
                'project_id' => $project->id,
                'name' => $column['name'],
                'color' => $column['color'],
                'order' => $column['order'],
            ]);
        }
    }

    public function getColumns(Project $project): Collection
    {
        return $project->columns()->orderBy('order')->get();
    }

    public function createColumn(Project $project, array $data): ProjectColumn
    {
        if (! isset($data['order'])) {
            $data['order'] = ($project->columns()->max('order') ?? 0) + 1;
        }

        return ProjectColumn::create([
            'project_id' => $project->id,
            'name' => $data['name'],
            'color' => $data['color'] ?? '#6B7280',
            'order' => $data['order'],
            'task_limit' => $data['task_limit'] ?? null,
        ]);
    }

    public function updateColumn(ProjectColumn $column, array $data): ProjectColumn
    {
        $column->update($data);

        return $column->fresh();
    }

    public function deleteColumn(ProjectColumn $column): bool
    {
        if ($column->tasks()->count() > 0) {
            throw new \InvalidArgumentException('Cannot delete a column that has tasks. Move tasks first.');
        }

        return $column->delete();
    }

    public function reorderColumns(Project $project, array $orderedIds): void
    {
        foreach ($orderedIds as $index => $columnId) {
            ProjectColumn::where('id', $columnId)
                ->where('project_id', $project->id)
                ->update(['order' => $index + 1]);
        }
    }
}
