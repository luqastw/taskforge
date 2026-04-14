<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Task;
use App\Notifications\DeadlineApproachingNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Notification;

class CheckUpcomingDeadlines implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function handle(): void
    {
        $tasks = Task::query()
            ->withoutGlobalScopes()
            ->whereNotNull('deadline')
            ->whereBetween('deadline', [now(), now()->addHours(24)])
            ->whereHas('assignees')
            ->with('assignees')
            ->get();

        foreach ($tasks as $task) {
            $assignees = $task->assignees->filter(function ($user) use ($task) {
                // Skip if user already has a deadline notification for this task
                return $user->notifications()
                    ->where('type', DeadlineApproachingNotification::class)
                    ->where('data->task_id', $task->id)
                    ->doesntExist();
            });

            if ($assignees->isNotEmpty()) {
                Notification::send($assignees, new DeadlineApproachingNotification($task));
            }
        }
    }
}
