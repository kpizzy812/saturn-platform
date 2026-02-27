<x-emails.layout>
Database backup for {{ $database_name }} with schedule **{{ $frequency }}** has not succeeded in over {{ $stale_hours }} hours.

### Last Successful Backup

{{ $last_success_at }}

### What to do

1. Check if the backup schedule is still enabled
2. Review the latest backup execution logs for errors
3. Verify the database container is running and accessible
4. Manually trigger a backup to confirm it works
</x-emails.layout>
