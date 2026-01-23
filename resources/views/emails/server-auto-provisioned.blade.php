<x-emails.layout>
**New Server Auto-Provisioned**

A new server has been automatically created due to resource constraints on your existing server.

**New Server Details:**
- Name: {{ $new_server_name }}
- IP Address: {{ $new_server_ip }}

**Trigger Information:**
- Triggered by: {{ $trigger_server_name }}
- Reason: {{ $trigger_reason }}
@if(!empty($trigger_metrics))
- Metrics at trigger time:
@if(isset($trigger_metrics['cpu']))
  - CPU: {{ round($trigger_metrics['cpu'], 1) }}%
@endif
@if(isset($trigger_metrics['memory']))
  - Memory: {{ round($trigger_metrics['memory'], 1) }}%
@endif
@endif

The new server is now ready to receive deployments.

(You can manage auto-provisioning settings in Instance Settings.)
</x-emails.layout>
