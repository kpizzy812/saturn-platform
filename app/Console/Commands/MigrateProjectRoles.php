<?php

namespace App\Console\Commands;

use App\Models\Project;
use App\Models\Team;
use App\Models\TeamUser;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class MigrateProjectRoles extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'saturn:migrate-roles {--dry-run : Show what would be migrated without making changes}';

    /**
     * The console command description.
     */
    protected $description = 'Migrate team member roles to project member roles';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $dryRun = $this->option('dry-run');

        if ($dryRun) {
            $this->info('DRY RUN MODE - No changes will be made');
        }

        $this->info('Starting role migration...');

        // Get all teams with their members and projects
        $teams = Team::with(['members', 'projects'])->get();

        $totalMigrated = 0;
        $totalSkipped = 0;

        foreach ($teams as $team) {
            $this->line("Processing team: {$team->name} (ID: {$team->id})");

            if ($team->projects->isEmpty()) {
                $this->line('  No projects in this team, skipping...');

                continue;
            }

            foreach ($team->members as $member) {
                /** @var TeamUser|null $memberPivot */
                $memberPivot = data_get($member, 'pivot');
                $teamRole = $memberPivot?->role;

                foreach ($team->projects as $project) {
                    // Check if already exists in project_user
                    $exists = DB::table('project_user')
                        ->where('project_id', $project->id)
                        ->where('user_id', $member->id)
                        ->exists();

                    if ($exists) {
                        $this->line("  User {$member->email} already has role in project {$project->name}, skipping...");
                        $totalSkipped++;

                        continue;
                    }

                    // Map team role to project role
                    $projectRole = $this->mapTeamRoleToProjectRole($teamRole);

                    $this->info("  Migrating: {$member->email} ({$teamRole} -> {$projectRole}) to project {$project->name}");

                    if (! $dryRun) {
                        DB::table('project_user')->insert([
                            'project_id' => $project->id,
                            'user_id' => $member->id,
                            'role' => $projectRole,
                            'environment_permissions' => null,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }

                    $totalMigrated++;
                }
            }
        }

        $this->newLine();
        $this->info('Migration complete!');
        $this->info("Total migrated: {$totalMigrated}");
        $this->info("Total skipped (already exists): {$totalSkipped}");

        if ($dryRun) {
            $this->warn('This was a dry run. Run without --dry-run to apply changes.');
        }

        return Command::SUCCESS;
    }

    /**
     * Map team role to project role.
     */
    private function mapTeamRoleToProjectRole(string $teamRole): string
    {
        return match ($teamRole) {
            'owner' => 'owner',
            'admin' => 'admin',
            'developer' => 'developer',
            'member' => 'member',
            'viewer' => 'viewer',
            default => 'member',
        };
    }
}
