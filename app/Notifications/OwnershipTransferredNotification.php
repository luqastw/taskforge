<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OwnershipTransferredNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        protected Tenant $tenant,
        protected User $previousOwner,
        protected User $newOwner,
        protected bool $isNewOwner
    ) {}

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        if ($this->isNewOwner) {
            return (new MailMessage)
                ->subject('You are now the owner of '.$this->tenant->name)
                ->greeting('Hello '.$this->newOwner->name.'!')
                ->line('You have been assigned as the new owner of '.$this->tenant->name.'.')
                ->line('The previous owner, '.$this->previousOwner->name.', has transferred ownership to you.')
                ->line('As the owner, you now have full control over the tenant, including:')
                ->line('- Managing all members and their roles')
                ->line('- Updating tenant settings')
                ->line('- Transferring ownership to another member')
                ->action('Go to Dashboard', url('/'))
                ->line('Thank you for using TaskForge!');
        }

        return (new MailMessage)
            ->subject('Ownership transferred for '.$this->tenant->name)
            ->greeting('Hello '.$this->previousOwner->name.'!')
            ->line('You have successfully transferred ownership of '.$this->tenant->name.' to '.$this->newOwner->name.'.')
            ->line('Your role has been changed to Admin. You still have access to most features, but you can no longer:')
            ->line('- Transfer ownership')
            ->line('- Delete the tenant')
            ->action('Go to Dashboard', url('/'))
            ->line('Thank you for using TaskForge!');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'ownership_transferred',
            'tenant_id' => $this->tenant->id,
            'tenant_name' => $this->tenant->name,
            'previous_owner_id' => $this->previousOwner->id,
            'previous_owner_name' => $this->previousOwner->name,
            'new_owner_id' => $this->newOwner->id,
            'new_owner_name' => $this->newOwner->name,
            'is_new_owner' => $this->isNewOwner,
            'message' => $this->isNewOwner
                ? 'You are now the owner of '.$this->tenant->name
                : 'You transferred ownership of '.$this->tenant->name.' to '.$this->newOwner->name,
        ];
    }
}
