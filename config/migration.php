<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Pre-Migration Backup Timeout
    |--------------------------------------------------------------------------
    |
    | Maximum seconds to wait for a pre-migration backup to complete before
    | treating it as timed out. Default is 300 seconds (5 minutes).
    | The backup result is treated as a warning, not a blocker.
    |
    */
    'pre_backup_timeout_seconds' => (int) env('PRE_MIGRATION_BACKUP_TIMEOUT', 300),
];
