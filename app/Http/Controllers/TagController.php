<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Resources\TagResource;
use App\Models\Tag;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class TagController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Tag::class);

        $query = Tag::withCount('tasks');

        if ($request->has('search')) {
            $query->where('name', 'like', "%{$request->search}%");
        }

        $tags = $query->orderBy('name')->paginate($request->integer('per_page', 15));

        return TagResource::collection($tags);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', Tag::class);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'color' => ['nullable', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
        ]);

        $validated['tenant_id'] = $request->user()->tenant_id;

        $tag = Tag::create($validated);

        return $this->createdResponse(new TagResource($tag));
    }

    public function show(Tag $tag): TagResource
    {
        $this->authorize('view', $tag);

        return new TagResource($tag->loadCount('tasks'));
    }

    public function update(Request $request, Tag $tag): TagResource
    {
        $this->authorize('update', $tag);

        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'color' => ['nullable', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
        ]);

        $tag->update($validated);

        return new TagResource($tag->fresh());
    }

    public function destroy(Tag $tag): Response
    {
        $this->authorize('delete', $tag);

        $tag->delete();

        return response()->noContent();
    }
}
