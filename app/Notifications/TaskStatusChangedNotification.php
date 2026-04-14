<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Task;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TaskStatusChangedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        protected Task $task,
        protected User $changedBy,
        protected string $oldColumn,
        protected string $newColumn,
    ) {}

    public function via(object $notifiable): array
    {
        $channels = ['database'];

        $prefs = $notifiable->notification_preferences ?? [];
        if (($prefs['email_task_status'] ?? false) !== false) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Task moved: {$this->task->title}")
            ->greeting("Hello {$notifiable->name},")
            ->line("{$this->changedBy->name} moved \"{$this->task->title}\" from \"{$this->oldColumn}\" to \"{$this->newColumn}\".")
            ->action('View Task', url("/tasks/{$this->task->id}"));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'task_status_changed',
            'task_id' => $this->task->id,
            'task_title' => $this->task->title,
            'changed_by_id' => $this->changedBy->id,
            'changed_by_name' => $this->changedBy->name,
            'old_column' => $this->oldColumn,
            'new_column' => $this->newColumn,
            'message' => "{$this->changedBy->name} moved \"{$this->task->title}\" to \"{$this->newColumn}\"",
        ];
    }
}
