<x-mail::message>
# You've Been Invited!

**{{ $inviterName }}** has invited you to join **{{ $tenantName }}** on TaskForge as a **{{ $role }}**.

To accept this invitation and create your account, click the button below:

<x-mail::button :url="$acceptUrl">
Accept Invitation
</x-mail::button>

This invitation will expire on **{{ $expiresAt }}**.

If you did not expect this invitation, you can safely ignore this email.

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
