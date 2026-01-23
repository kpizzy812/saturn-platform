<x-emails.layout>
@if($level === 'critical')
**CRITICAL ALERT**

@endif
Your server ({{ $name }}) has high memory usage ({{ $memory_usage }}% used). {{ ucfirst($level) }} threshold is {{ $threshold }}%.

Please investigate processes consuming high memory or consider scaling your server.

(You can change the threshold in Instance Settings.)
</x-emails.layout>
