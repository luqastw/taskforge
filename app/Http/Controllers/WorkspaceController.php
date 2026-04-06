<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\StoreWorkspaceRequest;
use App\Http\Requests\UpdateWorkspaceRequest;
use App\Http\Resources\WorkspaceResource;
use App\Models\Workspace;
use App\Services\WorkspaceService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class WorkspaceController extends Controller
{
    public function __construct(
        private readonly WorkspaceService $workspaceService
    ) {}

    /**
     * Display a listing of workspaces.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Workspace::class);

        $perPage = (int) $request->get('per_page', 15);
        $filters = $request->only(['name']);

        $workspaces = $this->workspaceService->getAllWorkspaces($perPage, $filters);

        return WorkspaceResource::collection($workspaces);
    }

    /**
     * Store a newly created workspace.
     */
    public function store(StoreWorkspaceRequest $request): WorkspaceResource
    {
        $workspace = $this->workspaceService->createWorkspace(
            $request->validated()
        );

        return new WorkspaceResource($workspace);
    }

    /**
     * Display the specified workspace.
     */
    public function show(Workspace $workspace): WorkspaceResource
    {
        $this->authorize('view', $workspace);

        $workspace = $this->workspaceService->getWorkspaceById($workspace->id);

        return new WorkspaceResource($workspace);
    }

    /**
     * Update the specified workspace.
     */
    public function update(
        UpdateWorkspaceRequest $request,
        Workspace $workspace
    ): WorkspaceResource {
        $workspace = $this->workspaceService->updateWorkspace(
            $workspace,
            $request->validated()
        );

        return new WorkspaceResource($workspace);
    }

    /**
     * Remove the specified workspace (soft delete).
     */
    public function destroy(Workspace $workspace): Response
    {
        $this->authorize('delete', $workspace);

        $this->workspaceService->deleteWorkspace($workspace);

        return response()->noContent();
    }
}
