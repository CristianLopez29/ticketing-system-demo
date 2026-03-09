@component('mail::message')
# Overdue assignment reminder

You have a candidate assignment that is overdue.

- Candidate: {{ $candidateName }} ({{ $candidateEmail }})
- Deadline: {{ \Carbon\Carbon::instance($deadline)->format('Y-m-d H:i:s') }}

Please review this candidate as soon as possible.

Thanks,
{{ config('app.name') }}
@endcomponent
