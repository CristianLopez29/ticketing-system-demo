@component('mail::message')
# New candidate assigned

A new candidate has been assigned to you for evaluation.

- Candidate: {{ $candidateName }} ({{ $candidateEmail }})

@component('mail::button', ['url' => url('/')])
Open Platform
@endcomponent

Thanks,
{{ config('app.name') }}
@endcomponent
