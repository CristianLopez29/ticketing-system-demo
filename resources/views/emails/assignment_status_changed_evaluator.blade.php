@component('mail::message')
# Candidate assignment updated

The status of a candidate assignment has changed.

- Candidate: {{ $candidateName }} ({{ $candidateEmail }})
- Previous status: {{ $previousStatus }}
- New status: {{ $newStatus }}

@component('mail::button', ['url' => url('/')])
Open Platform
@endcomponent

Thanks,
{{ config('app.name') }}
@endcomponent
