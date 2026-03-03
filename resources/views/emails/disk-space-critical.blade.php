<x-emails.layout>
**CRITICAL:** Your server ({{ $name }}) disk usage has reached **{{ $disk_usage }}%** (critical threshold: {{ $threshold }}%).

New deployments to this server are **blocked** until disk space is freed.

Please free disk space immediately to resume deployments. Here are some [tips](#/knowledge-base/server/automated-cleanup).
</x-emails.layout>
