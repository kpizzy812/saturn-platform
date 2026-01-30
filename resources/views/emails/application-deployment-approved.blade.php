<x-emails.layout>
Your deployment for **{{ $name }}** in environment **{{ $environment }}** has been approved by **{{ $approved_by }}** and is now proceeding.

@if ($note)
**Note:** {{ $note }}
@endif

[View Deployment Logs]({{ $deployment_url }})

</x-emails.layout>
