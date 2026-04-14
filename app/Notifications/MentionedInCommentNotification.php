<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Comment;
use App\Models\Task;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class MentionedInCommentNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        protected Comment $comment,
        protected Task $task,
    ) {}

    public function via(object $notifiable): array
    {
        $channels = ['database'];

        $prefs = $notifiable->notification_preferences ?? [];
        if (($prefs['email_mentioned'] ?? true) !== false) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("You were mentioned in \"{$this->task->title}\"")
            ->greeting("Hello {$notifiable->name},")
            ->line("{$this->comment->user->name} mentioned you in a comment on \"{$this->task->title}\":")
            ->line("> ".str($this->comment->content)->limit(200))
            ->action('View Task', url("/tasks/{$this->task->id}"));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'mentioned_in_comment',
            'task_id' => $this->task->id,
            'task_title' => $this->task->title,
            'comment_id' => $this->comment->id,
            'author_id' => $this->comment->user_id,
            'author_name' => $this->comment->user->name,
            'message' => "{$this->comment->user->name} mentioned you in \"{$this->task->title}\"",
        ];
    }
}
