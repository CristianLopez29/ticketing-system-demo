@component('mail::message')
# Evaluators Report Ready

The consolidated evaluators report has been generated.

@component('mail::button', ['url' => $downloadUrl])
Download Report
@endcomponent

Thanks,
{{ config('app.name') }}
@endcomponent
