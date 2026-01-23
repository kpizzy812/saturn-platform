<x-emails.layout>
@if($level === 'critical')
**CRITICAL ALERT**

@endif
Your server ({{ $name }}) has high CPU usage ({{ $cpu_usage }}% used). {{ ucfirst($level) }} threshold is {{ $threshold }}%.

Please investigate processes consuming high CPU.

(You can change the threshold in Instance Settings.)
</x-emails.layout>
