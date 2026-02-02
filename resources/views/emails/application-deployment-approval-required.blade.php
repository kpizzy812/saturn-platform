<x-emails.layout>
# Deployment Approval Required

A deployment is waiting for your approval.

**Application:** {{ $name }}

**Project:** {{ $project }}

**Environment:** {{ $environment }}

@if($requested_by)
**Requested by:** {{ $requested_by }}
@endif

An admin must approve this deployment before it can proceed.

[View Deployment]({{ $deployment_url }})

[Approve/Reject]({{ $approval_url }})

</x-emails.layout>
