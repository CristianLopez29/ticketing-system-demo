@component('mail::message')
# Your candidacy has been assigned

Your candidacy has been assigned to an evaluator for review.

- Evaluator: {{ $evaluatorName }}

We will notify you once a decision has been made.

Thanks,
{{ config('app.name') }}
@endcomponent
