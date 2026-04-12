<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Task;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class TaskAttachmentController extends Controller
{
    public function index(Task $task): JsonResponse
    {
        $this->authorize('view', $task);

        $media = $task->getMedia('attachments')->map(fn (Media $media) => [
            'id' => $media->id,
            'name' => $media->name,
            'file_name' => $media->file_name,
            'mime_type' => $media->mime_type,
            'size' => $media->size,
            'url' => $media->getUrl(),
            'created_at' => $media->created_at?->toIso8601String(),
        ]);

        return $this->successResponse($media);
    }

    public function store(Request $request, Task $task): JsonResponse
    {
        $this->authorize('update', $task);

        $request->validate([
            'file' => ['required', 'file', 'max:10240'],
        ]);

        $media = $task->addMediaFromRequest('file')
            ->toMediaCollection('attachments');

        activity('task_attachment_added')
            ->performedOn($task)
            ->causedBy($request->user())
            ->withProperties([
                'file_name' => $media->file_name,
                'mime_type' => $media->mime_type,
                'size' => $media->size,
            ])
            ->log("Attachment {$media->file_name} added to task {$task->title}");

        return $this->createdResponse([
            'id' => $media->id,
            'name' => $media->name,
            'file_name' => $media->file_name,
            'mime_type' => $media->mime_type,
            'size' => $media->size,
            'url' => $media->getUrl(),
            'created_at' => $media->created_at?->toIso8601String(),
        ]);
    }

    public function destroy(Request $request, Task $task, int $mediaId): Response
    {
        $this->authorize('update', $task);

        $media = $task->media()->findOrFail($mediaId);

        activity('task_attachment_removed')
            ->performedOn($task)
            ->causedBy($request->user())
            ->withProperties([
                'file_name' => $media->file_name,
            ])
            ->log("Attachment {$media->file_name} removed from task {$task->title}");

        $media->delete();

        return response()->noContent();
    }
}
