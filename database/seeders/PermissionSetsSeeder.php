<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\PermissionSet;
use App\Models\Team;
use Illuminate\Database\Seeder;

/**
 * Seeds the default system permission sets for each team.
 * Creates 5 built-in permission sets: Owner, Admin, Developer, Member, Viewer
 */
class PermissionSetsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // First ensure permissions exist
        $this->call(PermissionsSeeder::class);

        // Create system permission sets for each team
        $teams = Team::all();

        foreach ($teams as $team) {
            $this->createSystemSetsForTeam($team);
        }
    }

    /**
     * Create system permission sets for a team.
     */
    public function createSystemSetsForTeam(Team $team): void
    {
        $sets = $this->getSystemPermissionSets();

        foreach ($sets as $setData) {
            $permissionSet = PermissionSet::updateOrCreate(
                [
                    'scope_type' => 'team',
                    'scope_id' => $team->id,
                    'slug' => $setData['slug'],
                ],
                [
                    'name' => $setData['name'],
                    'description' => $setData['description'],
                    'is_system' => true,
                    'color' => $setData['color'],
                    'icon' => $setData['icon'],
                ]
            );

            // Sync permissions
            $permissionKeys = $setData['permissions'];
            $permissions = Permission::whereIn('key', $permissionKeys)->get();
            $permissionSet->permissions()->sync($permissions->pluck('id'));
        }
    }

    /**
     * Get the system permission sets configuration.
     */
    private function getSystemPermissionSets(): array
    {
        // All permissions
        $allPermissions = [
            // Applications
            'applications.view', 'applications.create', 'applications.update', 'applications.delete',
            'applications.deploy', 'applications.logs', 'applications.env_vars', 'applications.env_vars_sensitive',
            // Databases
            'databases.view', 'databases.create', 'databases.update', 'databases.delete',
            'databases.manage', 'databases.backups',
            // Services
            'services.view', 'services.create', 'services.update', 'services.delete', 'services.manage',
            // Servers
            'servers.view', 'servers.create', 'servers.update', 'servers.delete', 'servers.proxy', 'servers.security',
            // Team
            'team.view', 'team.invite', 'team.manage_members', 'team.manage_roles', 'team.activity',
            // Settings
            'settings.view', 'settings.update', 'settings.integrations', 'settings.tokens', 'settings.notifications', 'settings.billing',
            // Projects
            'projects.view', 'projects.create', 'projects.update', 'projects.delete', 'projects.members',
            // Environments
            'environments.view', 'environments.create', 'environments.update', 'environments.delete',
        ];

        // Admin permissions (same as owner for most cases)
        $adminPermissions = $allPermissions;

        // Developer permissions - can deploy and manage resources, but not delete or manage team
        $developerPermissions = [
            // Applications - full except delete
            'applications.view', 'applications.create', 'applications.update',
            'applications.deploy', 'applications.logs', 'applications.env_vars',
            // Databases - full except delete and backups
            'databases.view', 'databases.create', 'databases.update', 'databases.manage',
            // Services - full except delete
            'services.view', 'services.create', 'services.update', 'services.manage',
            // Servers - view only
            'servers.view',
            // Team - view only
            'team.view', 'team.activity',
            // Settings - view only
            'settings.view',
            // Projects - view and update
            'projects.view', 'projects.update',
            // Environments - view and update
            'environments.view', 'environments.create', 'environments.update',
        ];

        // Member permissions - basic operations, no create/delete
        $memberPermissions = [
            // Applications - view, deploy, logs only
            'applications.view', 'applications.deploy', 'applications.logs',
            // Databases - view and manage only
            'databases.view', 'databases.manage',
            // Services - view and manage only
            'services.view', 'services.manage',
            // Servers - view only
            'servers.view',
            // Team - view only
            'team.view', 'team.activity',
            // Settings - view only
            'settings.view',
            // Projects - view only
            'projects.view',
            // Environments - view only
            'environments.view',
        ];

        // Viewer permissions - read-only access
        $viewerPermissions = [
            'applications.view', 'applications.logs',
            'databases.view',
            'services.view',
            'servers.view',
            'team.view', 'team.activity',
            'settings.view',
            'projects.view',
            'environments.view',
        ];

        return [
            [
                'slug' => 'owner',
                'name' => 'Owner',
                'description' => 'Full control of the team and all resources',
                'color' => 'warning',
                'icon' => 'crown',
                'permissions' => $allPermissions,
            ],
            [
                'slug' => 'admin',
                'name' => 'Admin',
                'description' => 'Manage team members and settings',
                'color' => 'primary',
                'icon' => 'shield',
                'permissions' => $adminPermissions,
            ],
            [
                'slug' => 'developer',
                'name' => 'Developer',
                'description' => 'Deploy applications and manage resources',
                'color' => 'success',
                'icon' => 'code',
                'permissions' => $developerPermissions,
            ],
            [
                'slug' => 'member',
                'name' => 'Member',
                'description' => 'View resources and basic operations',
                'color' => 'foreground-muted',
                'icon' => 'user',
                'permissions' => $memberPermissions,
            ],
            [
                'slug' => 'viewer',
                'name' => 'Viewer',
                'description' => 'Read-only access to resources',
                'color' => 'info',
                'icon' => 'eye',
                'permissions' => $viewerPermissions,
            ],
        ];
    }
}
