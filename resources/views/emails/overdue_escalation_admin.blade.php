@component('mail::message')
# Escalation: Candidate assignment overdue

A candidate assignment has exceeded the overdue threshold.

- Candidate: {{ $candidateName }} ({{ $candidateEmail }})
- Evaluator: {{ $evaluatorName }} ({{ $evaluatorEmail }})
- Original deadline: {{ \Carbon\Carbon::instance($deadline)->format('Y-m-d H:i:s') }}
- Days overdue: {{ $daysOverdue }}

Please intervene to resolve this overdue assignment.

Thanks,
{{ config('app.name') }}
@endcomponent
