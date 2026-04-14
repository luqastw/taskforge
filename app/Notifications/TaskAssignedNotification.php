<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Task;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TaskAssignedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        protected Task $task,
        protected User $assignedBy,
    ) {}

    public function via(object $notifiable): array
    {
        return $this->channels($notifiable);
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Task assigned: {$this->task->title}")
            ->greeting("Hello {$notifiable->name},")
            ->line("{$this->assignedBy->name} assigned you to the task \"{$this->task->title}\".")
            ->line("Priority: {$this->task->priority}")
            ->when($this->task->deadline, fn (MailMessage $mail) => $mail->line("Deadline: {$this->task->deadline->format('Y-m-d')}"))
            ->action('View Task', url("/tasks/{$this->task->id}"));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'task_assigned',
            'task_id' => $this->task->id,
            'task_title' => $this->task->title,
            'assigned_by_id' => $this->assignedBy->id,
            'assigned_by_name' => $this->assignedBy->name,
            'message' => "{$this->assignedBy->name} assigned you to \"{$this->task->title}\"",
        ];
    }

    protected function channels(object $notifiable): array
    {
        $channels = ['database'];

        $prefs = $notifiable->notification_preferences ?? [];
        if (($prefs['email_task_assigned'] ?? true) !== false) {
            $channels[] = 'mail';
        }

        return $channels;
    }
}
