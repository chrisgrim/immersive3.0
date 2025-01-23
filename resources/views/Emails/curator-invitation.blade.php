@component('mail::message')
# Curator Invitation

You've been invited to be a curator for {{ $community->name }}.

@component('mail::button', ['url' => url("/curator-invitations/{$invitation->token}")])
Accept Invitation
@endcomponent

This invitation will expire in 7 days.

Thanks,<br>
{{ config('app.name') }}
@endcomponent