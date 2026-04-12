<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Resources\CommentResource;
use App\Models\Comment;
use App\Models\Task;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class CommentController extends Controller
{
    public function index(Task $task, Request $request): AnonymousResourceCollection
    {
        $this->authorize('view', $task);

        $comments = $task->comments()
            ->with('user.roles')
            ->orderBy('created_at', 'desc')
            ->paginate($request->integer('per_page', 15));

        return CommentResource::collection($comments);
    }

    public function store(Request $request, Task $task): JsonResponse
    {
        $this->authorize('create', Comment::class);
        $this->authorize('view', $task);

        $validated = $request->validate([
            'content' => ['required', 'string', 'max:10000'],
        ]);

        $comment = Comment::create([
            'tenant_id' => $request->user()->tenant_id,
            'task_id' => $task->id,
            'user_id' => $request->user()->id,
            'content' => $validated['content'],
        ]);

        $comment->load('user.roles');

        return $this->createdResponse(new CommentResource($comment));
    }

    public function update(Request $request, Task $task, Comment $comment): CommentResource
    {
        $this->authorize('update', $comment);

        if ($comment->task_id !== $task->id) {
            abort(404);
        }

        $validated = $request->validate([
            'content' => ['required', 'string', 'max:10000'],
        ]);

        $comment->update($validated);

        return new CommentResource($comment->fresh()->load('user.roles'));
    }

    public function destroy(Task $task, Comment $comment): Response
    {
        $this->authorize('delete', $comment);

        if ($comment->task_id !== $task->id) {
            abort(404);
        }

        $comment->delete();

        return response()->noContent();
    }
}
