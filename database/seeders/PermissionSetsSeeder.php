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
            $this->assignExistingMembersToPermissionSets($team);
        }
    }

    /**
     * Assign existing team members to their corresponding permission sets.
     */
    private function assignExistingMembersToPermissionSets(Team $team): void
    {
        // Map old roles to permission set slugs
        $roleToSlugMap = [
            'owner' => 'owner',
            'admin' => 'admin',
            'developer' => 'developer',
            'member' => 'member',
            'viewer' => 'viewer',
        ];

        // Get team members with their roles from pivot
        $members = \DB::table('team_user')
            ->where('team_id', $team->id)
            ->get();

        foreach ($members as $member) {
            $role = $member->role ?? 'member';
            $slug = $roleToSlugMap[$role] ?? 'member';

            // Find the permission set
            $permissionSet = PermissionSet::forTeam($team->id)
                ->where('slug', $slug)
                ->first();

            if (! $permissionSet) {
                continue;
            }

            // Check if user is already assigned to this permission set
            $exists = \DB::table('permission_set_user')
                ->where('permission_set_id', $permissionSet->id)
                ->where('user_id', $member->user_id)
                ->where('scope_type', 'team')
                ->where('scope_id', $team->id)
                ->exists();

            if (! $exists) {
                \DB::table('permission_set_user')->insert([
                    'permission_set_id' => $permissionSet->id,
                    'user_id' => $member->user_id,
                    'scope_type' => 'team',
                    'scope_id' => $team->id,
                    'environment_overrides' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
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
            'applications.terminal',
            // Databases
            'databases.view', 'databases.create', 'databases.update', 'databases.delete',
            'databases.manage', 'databases.backups', 'databases.credentials', 'databases.env_vars',
            // Services
            'services.view', 'services.create', 'services.update', 'services.delete', 'services.manage',
            'services.env_vars', 'services.env_vars_sensitive', 'services.terminal',
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

        // Admin permissions - all except owner-only (billing, manage_roles, delete servers/databases)
        $adminPermissions = [
            // Applications - full access
            'applications.view', 'applications.create', 'applications.update', 'applications.delete',
            'applications.deploy', 'applications.logs', 'applications.env_vars', 'applications.env_vars_sensitive',
            'applications.terminal',
            // Databases - full except delete (owner only for data safety)
            'databases.view', 'databases.create', 'databases.update',
            'databases.manage', 'databases.backups', 'databases.credentials', 'databases.env_vars',
            // Services - full access
            'services.view', 'services.create', 'services.update', 'services.delete', 'services.manage',
            'services.env_vars', 'services.env_vars_sensitive', 'services.terminal',
            // Servers - full except delete (owner only for infrastructure safety)
            'servers.view', 'servers.create', 'servers.update', 'servers.proxy', 'servers.security',
            // Team - manage members but not roles (owner only)
            'team.view', 'team.invite', 'team.manage_members', 'team.activity',
            // Settings - full except billing (owner only)
            'settings.view', 'settings.update', 'settings.integrations', 'settings.tokens', 'settings.notifications',
            // Projects - full access
            'projects.view', 'projects.create', 'projects.update', 'projects.delete', 'projects.members',
            // Environments - full access
            'environments.view', 'environments.create', 'environments.update', 'environments.delete',
        ];

        // Developer permissions - can deploy and manage resources, but not delete or manage team
        // NO access to: sensitive env vars, credentials, terminal, delete operations, server management
        $developerPermissions = [
            // Applications - full except delete, sensitive, terminal
            'applications.view', 'applications.create', 'applications.update',
            'applications.deploy', 'applications.logs', 'applications.env_vars',
            // Databases - full except delete, backups, credentials
            'databases.view', 'databases.create', 'databases.update', 'databases.manage',
            'databases.env_vars',
            // Services - full except delete, sensitive, terminal
            'services.view', 'services.create', 'services.update', 'services.manage',
            'services.env_vars',
            // Servers - view only (no create/update/delete/proxy/security)
            'servers.view',
            // Team - view only
            'team.view', 'team.activity',
            // Settings - view only
            'settings.view',
            // Projects - view and update
            'projects.view', 'projects.update',
            // Environments - view and update (no create/delete for production restriction)
            'environments.view', 'environments.update',
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
