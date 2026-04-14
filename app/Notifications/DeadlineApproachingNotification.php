<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Task;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class DeadlineApproachingNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        protected Task $task,
    ) {}

    public function via(object $notifiable): array
    {
        $channels = ['database'];

        $prefs = $notifiable->notification_preferences ?? [];
        if (($prefs['email_deadline'] ?? true) !== false) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Deadline approaching: {$this->task->title}")
            ->greeting("Hello {$notifiable->name},")
            ->line("The task \"{$this->task->title}\" is due on {$this->task->deadline->format('Y-m-d H:i')}.")
            ->line('Less than 24 hours remaining.')
            ->action('View Task', url("/tasks/{$this->task->id}"));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'deadline_approaching',
            'task_id' => $this->task->id,
            'task_title' => $this->task->title,
            'deadline' => $this->task->deadline->toIso8601String(),
            'message' => "Task \"{$this->task->title}\" is due in less than 24 hours",
        ];
    }
}
