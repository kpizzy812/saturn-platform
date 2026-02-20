<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CleanupDatabase extends Command
{
    protected $signature = 'cleanup:database {--yes} {--keep-days=}';

    protected $description = 'Cleanup database';

    public function handle()
    {
        if ($this->option('yes')) {
            echo "Running database cleanup...\n";
        } else {
            echo "Running database cleanup in dry-run mode...\n";
        }
        if (isCloud()) {
            // Later on we can increase this to 180 days or dynamically set
            $keep_days = $this->option('keep-days') ?? 60;
        } else {
            $keep_days = $this->option('keep-days') ?? 60;
        }
        echo "Keep days: $keep_days\n";
        // Cleanup failed jobs table
        $failed_jobs = DB::table('failed_jobs')->where('failed_at', '<', now()->subDays(1));
        $count = $failed_jobs->count();
        echo "Delete $count entries from failed_jobs.\n";
        if ($this->option('yes')) {
            $failed_jobs->delete();
        }

        // Cleanup sessions table
        $sessions = DB::table('sessions')->where('last_activity', '<', now()->subDays($keep_days)->timestamp);
        $count = $sessions->count();
        echo "Delete $count entries from sessions.\n";
        if ($this->option('yes')) {
            $sessions->delete();
        }

        // Cleanup activity_log table
        $activity_log = DB::table('activity_log')->where('created_at', '<', now()->subDays($keep_days))->orderBy('created_at', 'desc')->skip(10);
        $count = $activity_log->count();
        echo "Delete $count entries from activity_log.\n";
        if ($this->option('yes')) {
            $activity_log->delete();
        }

        // Cleanup application_deployment_queues table
        $application_deployment_queues = DB::table('application_deployment_queues')->where('created_at', '<', now()->subDays($keep_days))->orderBy('created_at', 'desc')->skip(10);
        $count = $application_deployment_queues->count();
        echo "Delete $count entries from application_deployment_queues.\n";
        if ($this->option('yes')) {
            $application_deployment_queues->delete();
        }

        // Cleanup scheduled_task_executions table
        $scheduled_task_executions = DB::table('scheduled_task_executions')->where('created_at', '<', now()->subDays($keep_days))->orderBy('created_at', 'desc');
        $count = $scheduled_task_executions->count();
        echo "Delete $count entries from scheduled_task_executions.\n";
        if ($this->option('yes')) {
            $scheduled_task_executions->delete();
        }

        // Cleanup deployment_log_entries for old deployments
        $deployment_log_entries = DB::table('deployment_log_entries')->where('created_at', '<', now()->subDays($keep_days));
        $count = $deployment_log_entries->count();
        echo "Delete $count entries from deployment_log_entries.\n";
        if ($this->option('yes')) {
            $deployment_log_entries->delete();
        }

        // Cleanup audit_logs table
        $audit_logs = DB::table('audit_logs')->where('created_at', '<', now()->subDays($keep_days));
        $count = $audit_logs->count();
        echo "Delete $count entries from audit_logs.\n";
        if ($this->option('yes')) {
            $audit_logs->delete();
        }

        // Cleanup login_history table
        $login_history = DB::table('login_history')->where('logged_at', '<', now()->subDays($keep_days));
        $count = $login_history->count();
        echo "Delete $count entries from login_history.\n";
        if ($this->option('yes')) {
            $login_history->delete();
        }

        // Cleanup scheduled_database_backup_executions table
        $backup_executions = DB::table('scheduled_database_backup_executions')->where('created_at', '<', now()->subDays($keep_days));
        $count = $backup_executions->count();
        echo "Delete $count entries from scheduled_database_backup_executions.\n";
        if ($this->option('yes')) {
            $backup_executions->delete();
        }

        // Cleanup server_health_checks table (keep only 30 days of monitoring data)
        $health_checks = DB::table('server_health_checks')->where('created_at', '<', now()->subDays(30));
        $count = $health_checks->count();
        echo "Delete $count entries from server_health_checks.\n";
        if ($this->option('yes')) {
            $health_checks->delete();
        }
    }
}
