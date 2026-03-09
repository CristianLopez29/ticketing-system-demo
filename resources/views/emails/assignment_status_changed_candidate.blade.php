@component('mail::message')
# Update on your candidacy

The status of your candidacy has changed.

- Evaluator: {{ $evaluatorName }}
- Previous status: {{ $previousStatus }}
- New status: {{ $newStatus }}

You can check your status by logging into the platform.

Thanks,
{{ config('app.name') }}
@endcomponent
