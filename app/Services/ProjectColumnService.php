<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Project;
use App\Models\ProjectColumn;

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
}
