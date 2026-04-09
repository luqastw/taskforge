<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\StoreProjectColumnRequest;
use App\Http\Requests\UpdateProjectColumnRequest;
use App\Http\Resources\ProjectColumnResource;
use App\Models\Project;
use App\Models\ProjectColumn;
use App\Services\ProjectColumnService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ProjectColumnController extends Controller
{
    public function __construct(
        private readonly ProjectColumnService $columnService
    ) {}

    /**
     * List all columns of a project.
     */
    public function index(Project $project): AnonymousResourceCollection
    {
        $this->authorize('view', $project);

        $columns = $this->columnService->getColumns($project);

        return ProjectColumnResource::collection($columns);
    }

    /**
     * Create a new column for a project.
     */
    public function store(StoreProjectColumnRequest $request, Project $project): JsonResponse
    {
        $column = $this->columnService->createColumn($project, $request->validated());

        activity('project_column_created')
            ->performedOn($project)
            ->causedBy($request->user())
            ->withProperties([
                'column_id' => $column->id,
                'column_name' => $column->name,
            ])
            ->log("Column {$column->name} was created in project {$project->name}");

        return $this->createdResponse(
            new ProjectColumnResource($column),
            'Column created successfully'
        );
    }

    /**
     * Show a specific column.
     */
    public function show(Project $project, ProjectColumn $column): JsonResponse
    {
        $this->authorize('view', $project);

        if ($column->project_id !== $project->id) {
            return $this->errorResponse('Column does not belong to this project', 404);
        }

        $column->loadCount('tasks');

        return $this->successResponse(
            new ProjectColumnResource($column),
            'Column retrieved successfully'
        );
    }

    /**
     * Update a column.
     */
    public function update(UpdateProjectColumnRequest $request, Project $project, ProjectColumn $column): JsonResponse
    {
        if ($column->project_id !== $project->id) {
            return $this->errorResponse('Column does not belong to this project', 404);
        }

        $column = $this->columnService->updateColumn($column, $request->validated());

        activity('project_column_updated')
            ->performedOn($project)
            ->causedBy($request->user())
            ->withProperties([
                'column_id' => $column->id,
                'column_name' => $column->name,
            ])
            ->log("Column {$column->name} was updated in project {$project->name}");

        return $this->successResponse(
            new ProjectColumnResource($column),
            'Column updated successfully'
        );
    }

    /**
     * Delete a column.
     */
    public function destroy(Request $request, Project $project, ProjectColumn $column): JsonResponse
    {
        $this->authorize('update', $project);

        if ($column->project_id !== $project->id) {
            return $this->errorResponse('Column does not belong to this project', 404);
        }

        try {
            $this->columnService->deleteColumn($column);
        } catch (\InvalidArgumentException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        }

        activity('project_column_deleted')
            ->performedOn($project)
            ->causedBy($request->user())
            ->withProperties([
                'column_id' => $column->id,
                'column_name' => $column->name,
            ])
            ->log("Column {$column->name} was deleted from project {$project->name}");

        return $this->successResponse(null, 'Column deleted successfully');
    }

    /**
     * Reorder columns.
     */
    public function reorder(Request $request, Project $project): JsonResponse
    {
        $this->authorize('update', $project);

        $request->validate([
            'column_ids' => ['required', 'array', 'min:1'],
            'column_ids.*' => ['integer', 'exists:project_columns,id'],
        ]);

        $this->columnService->reorderColumns($project, $request->column_ids);

        activity('project_columns_reordered')
            ->performedOn($project)
            ->causedBy($request->user())
            ->log("Columns were reordered in project {$project->name}");

        return $this->successResponse(
            ProjectColumnResource::collection($this->columnService->getColumns($project)),
            'Columns reordered successfully'
        );
    }
}
