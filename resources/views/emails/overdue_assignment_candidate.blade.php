@component('mail::message')
# Review delayed

The review of your candidacy is taking longer than expected.

- Evaluator: {{ $evaluatorName }}
- Original deadline: {{ \Carbon\Carbon::instance($deadline)->format('Y-m-d H:i:s') }}

We apologize for the delay and will notify you once a decision is made.

Thanks,
{{ config('app.name') }}
@endcomponent
