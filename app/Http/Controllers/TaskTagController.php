<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Resources\TagResource;
use App\Models\Tag;
use App\Models\Task;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class TaskTagController extends Controller
{
    public function index(Task $task): AnonymousResourceCollection
    {
        $this->authorize('view', $task);

        return TagResource::collection($task->tags()->paginate(request('per_page', 15)));
    }

    public function store(Request $request, Task $task): JsonResponse
    {
        $this->authorize('update', $task);

        $request->validate([
            'tag_ids' => ['required', 'array', 'min:1'],
            'tag_ids.*' => ['integer', 'exists:tags,id'],
        ]);

        $tags = Tag::where('tenant_id', $request->user()->tenant_id)
            ->whereIn('id', $request->tag_ids)
            ->pluck('id');

        if ($tags->isEmpty()) {
            return $this->errorResponse('No valid tags found in your tenant', 404);
        }

        $task->tags()->syncWithoutDetaching($tags);

        activity('task_tags_updated')
            ->performedOn($task)
            ->causedBy($request->user())
            ->withProperties(['tag_ids' => $tags->toArray()])
            ->log("Tags updated on task {$task->title}");

        return $this->successResponse(
            TagResource::collection($task->tags()->get()),
            'Tags attached to task successfully'
        );
    }

    public function destroy(Request $request, Task $task, Tag $tag): JsonResponse
    {
        $this->authorize('update', $task);

        if (! $task->tags()->where('tag_id', $tag->id)->exists()) {
            return $this->errorResponse('Tag is not attached to this task', 422);
        }

        $task->tags()->detach($tag->id);

        activity('task_tag_removed')
            ->performedOn($task)
            ->causedBy($request->user())
            ->withProperties(['tag_id' => $tag->id, 'tag_name' => $tag->name])
            ->log("Tag {$tag->name} removed from task {$task->title}");

        return $this->successResponse(null, 'Tag removed from task');
    }
}
