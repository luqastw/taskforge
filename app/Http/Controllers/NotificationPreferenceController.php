<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationPreferenceController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $defaults = [
            'email_task_assigned' => true,
            'email_mentioned' => true,
            'email_deadline' => true,
            'email_task_status' => false,
        ];

        $preferences = array_merge($defaults, $request->user()->notification_preferences ?? []);

        return $this->successResponse($preferences);
    }

    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email_task_assigned' => ['sometimes', 'boolean'],
            'email_mentioned' => ['sometimes', 'boolean'],
            'email_deadline' => ['sometimes', 'boolean'],
            'email_task_status' => ['sometimes', 'boolean'],
        ]);

        $current = $request->user()->notification_preferences ?? [];
        $merged = array_merge($current, $validated);

        $request->user()->update(['notification_preferences' => $merged]);

        return $this->successResponse($merged);
    }
}
