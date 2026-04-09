<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Mail\MemberInvitationMail;
use App\Models\Invitation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

class SendInvitationEmailJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public readonly Invitation $invitation,
        public readonly string $inviterName,
        public readonly string $tenantName
    ) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Mail::to($this->invitation->email)->send(
            new MemberInvitationMail(
                invitation: $this->invitation,
                inviterName: $this->inviterName,
                tenantName: $this->tenantName
            )
        );
    }
}
