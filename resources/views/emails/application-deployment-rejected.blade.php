<x-emails.layout>
Your deployment for **{{ $name }}** in environment **{{ $environment }}** has been rejected by **{{ $rejected_by }}**.

@if ($note)
**Reason:** {{ $note }}
@endif

[View Deployment Logs]({{ $deployment_url }})

</x-emails.layout>
