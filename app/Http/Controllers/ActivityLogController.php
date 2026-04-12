<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Resources\ActivityLogResource;
use App\Models\Project;
use App\Models\Task;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Spatie\Activitylog\Models\Activity;

class ActivityLogController extends Controller
{
    public function taskHistory(Task $task, Request $request): AnonymousResourceCollection
    {
        $this->authorize('view', $task);

        $activities = Activity::where('subject_type', Task::class)
            ->where('subject_id', $task->id)
            ->with('causer')
            ->orderBy('created_at', 'desc')
            ->paginate($request->integer('per_page', 15));

        return ActivityLogResource::collection($activities);
    }

    public function projectHistory(Project $project, Request $request): AnonymousResourceCollection
    {
        $this->authorize('view', $project);

        $activities = Activity::where('subject_type', Project::class)
            ->where('subject_id', $project->id)
            ->with('causer')
            ->orderBy('created_at', 'desc')
            ->paginate($request->integer('per_page', 15));

        return ActivityLogResource::collection($activities);
    }
}
