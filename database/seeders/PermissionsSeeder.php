<?php

namespace Database\Seeders;

use App\Models\Permission;
use Illuminate\Database\Seeder;

/**
 * Seeds the permissions catalog.
 * These are the base permissions available in the system.
 */
class PermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $permissions = $this->getPermissions();
        $sortOrder = 0;

        foreach ($permissions as $permData) {
            $sortOrder++;
            Permission::updateOrCreate(
                ['key' => $permData['key']],
                [
                    'name' => $permData['name'],
                    'description' => $permData['description'],
                    'resource' => $permData['resource'],
                    'action' => $permData['action'],
                    'category' => $permData['category'],
                    'is_sensitive' => $permData['is_sensitive'] ?? false,
                    'sort_order' => $sortOrder,
                ]
            );
        }
    }

    /**
     * Get the list of all permissions.
     */
    private function getPermissions(): array
    {
        return [
            // ===================
            // APPLICATIONS
            // ===================
            [
                'key' => 'applications.view',
                'name' => 'View Applications',
                'description' => 'View applications and their details',
                'resource' => 'applications',
                'action' => 'view',
                'category' => 'resources',
            ],
            [
                'key' => 'applications.create',
                'name' => 'Create Applications',
                'description' => 'Create new applications',
                'resource' => 'applications',
                'action' => 'create',
                'category' => 'resources',
            ],
            [
                'key' => 'applications.update',
                'name' => 'Update Applications',
                'description' => 'Modify application settings and configurations',
                'resource' => 'applications',
                'action' => 'update',
                'category' => 'resources',
            ],
            [
                'key' => 'applications.delete',
                'name' => 'Delete Applications',
                'description' => 'Delete applications permanently',
                'resource' => 'applications',
                'action' => 'delete',
                'category' => 'resources',
            ],
            [
                'key' => 'applications.deploy',
                'name' => 'Deploy Applications',
                'description' => 'Deploy, restart, and stop applications',
                'resource' => 'applications',
                'action' => 'deploy',
                'category' => 'resources',
            ],
            [
                'key' => 'applications.logs',
                'name' => 'View Application Logs',
                'description' => 'Access application and deployment logs',
                'resource' => 'applications',
                'action' => 'logs',
                'category' => 'resources',
            ],
            [
                'key' => 'applications.env_vars',
                'name' => 'Manage Environment Variables',
                'description' => 'View and edit non-sensitive environment variables',
                'resource' => 'applications',
                'action' => 'env_vars',
                'category' => 'resources',
            ],
            [
                'key' => 'applications.env_vars_sensitive',
                'name' => 'View Sensitive Variables',
                'description' => 'View sensitive/secret environment variables',
                'resource' => 'applications',
                'action' => 'env_vars_sensitive',
                'category' => 'resources',
                'is_sensitive' => true,
            ],
            [
                'key' => 'applications.terminal',
                'name' => 'Access Application Terminal',
                'description' => 'Access terminal/shell inside application containers',
                'resource' => 'applications',
                'action' => 'terminal',
                'category' => 'resources',
                'is_sensitive' => true,
            ],

            // ===================
            // DATABASES
            // ===================
            [
                'key' => 'databases.view',
                'name' => 'View Databases',
                'description' => 'View databases and their details',
                'resource' => 'databases',
                'action' => 'view',
                'category' => 'resources',
            ],
            [
                'key' => 'databases.create',
                'name' => 'Create Databases',
                'description' => 'Create new databases',
                'resource' => 'databases',
                'action' => 'create',
                'category' => 'resources',
            ],
            [
                'key' => 'databases.update',
                'name' => 'Update Databases',
                'description' => 'Modify database settings and configurations',
                'resource' => 'databases',
                'action' => 'update',
                'category' => 'resources',
            ],
            [
                'key' => 'databases.delete',
                'name' => 'Delete Databases',
                'description' => 'Delete databases permanently',
                'resource' => 'databases',
                'action' => 'delete',
                'category' => 'resources',
            ],
            [
                'key' => 'databases.manage',
                'name' => 'Manage Databases',
                'description' => 'Start, stop, and restart databases',
                'resource' => 'databases',
                'action' => 'manage',
                'category' => 'resources',
            ],
            [
                'key' => 'databases.backups',
                'name' => 'Manage Backups',
                'description' => 'Create, download, and restore database backups',
                'resource' => 'databases',
                'action' => 'backups',
                'category' => 'resources',
            ],
            [
                'key' => 'databases.credentials',
                'name' => 'View Database Credentials',
                'description' => 'View database passwords and connection strings',
                'resource' => 'databases',
                'action' => 'credentials',
                'category' => 'resources',
                'is_sensitive' => true,
            ],
            [
                'key' => 'databases.env_vars',
                'name' => 'Manage Database Variables',
                'description' => 'View and edit database environment variables',
                'resource' => 'databases',
                'action' => 'env_vars',
                'category' => 'resources',
            ],

            // ===================
            // SERVICES
            // ===================
            [
                'key' => 'services.view',
                'name' => 'View Services',
                'description' => 'View services and their details',
                'resource' => 'services',
                'action' => 'view',
                'category' => 'resources',
            ],
            [
                'key' => 'services.create',
                'name' => 'Create Services',
                'description' => 'Create new services from templates',
                'resource' => 'services',
                'action' => 'create',
                'category' => 'resources',
            ],
            [
                'key' => 'services.update',
                'name' => 'Update Services',
                'description' => 'Modify service settings and configurations',
                'resource' => 'services',
                'action' => 'update',
                'category' => 'resources',
            ],
            [
                'key' => 'services.delete',
                'name' => 'Delete Services',
                'description' => 'Delete services permanently',
                'resource' => 'services',
                'action' => 'delete',
                'category' => 'resources',
            ],
            [
                'key' => 'services.manage',
                'name' => 'Manage Services',
                'description' => 'Start, stop, and restart services',
                'resource' => 'services',
                'action' => 'manage',
                'category' => 'resources',
            ],
            [
                'key' => 'services.env_vars',
                'name' => 'Manage Service Variables',
                'description' => 'View and edit service environment variables',
                'resource' => 'services',
                'action' => 'env_vars',
                'category' => 'resources',
            ],
            [
                'key' => 'services.env_vars_sensitive',
                'name' => 'View Sensitive Service Variables',
                'description' => 'View sensitive/secret service environment variables',
                'resource' => 'services',
                'action' => 'env_vars_sensitive',
                'category' => 'resources',
                'is_sensitive' => true,
            ],
            [
                'key' => 'services.terminal',
                'name' => 'Access Service Terminal',
                'description' => 'Access terminal/shell inside service containers',
                'resource' => 'services',
                'action' => 'terminal',
                'category' => 'resources',
                'is_sensitive' => true,
            ],

            // ===================
            // SERVERS
            // ===================
            [
                'key' => 'servers.view',
                'name' => 'View Servers',
                'description' => 'View servers and their details',
                'resource' => 'servers',
                'action' => 'view',
                'category' => 'resources',
            ],
            [
                'key' => 'servers.create',
                'name' => 'Create Servers',
                'description' => 'Add new servers to the team',
                'resource' => 'servers',
                'action' => 'create',
                'category' => 'resources',
            ],
            [
                'key' => 'servers.update',
                'name' => 'Update Servers',
                'description' => 'Modify server settings and configurations',
                'resource' => 'servers',
                'action' => 'update',
                'category' => 'resources',
            ],
            [
                'key' => 'servers.delete',
                'name' => 'Delete Servers',
                'description' => 'Remove servers from the team',
                'resource' => 'servers',
                'action' => 'delete',
                'category' => 'resources',
            ],
            [
                'key' => 'servers.proxy',
                'name' => 'Manage Proxy',
                'description' => 'Configure Traefik/Caddy proxy settings',
                'resource' => 'servers',
                'action' => 'proxy',
                'category' => 'resources',
            ],
            [
                'key' => 'servers.security',
                'name' => 'Manage Server Security',
                'description' => 'Manage SSH keys, firewall, and security settings',
                'resource' => 'servers',
                'action' => 'security',
                'category' => 'resources',
                'is_sensitive' => true,
            ],

            // ===================
            // TEAM MANAGEMENT
            // ===================
            [
                'key' => 'team.view',
                'name' => 'View Team',
                'description' => 'See team members and their roles',
                'resource' => 'team',
                'action' => 'view',
                'category' => 'team',
            ],
            [
                'key' => 'team.invite',
                'name' => 'Invite Members',
                'description' => 'Send team invitations to new members',
                'resource' => 'team',
                'action' => 'invite',
                'category' => 'team',
            ],
            [
                'key' => 'team.manage_members',
                'name' => 'Manage Members',
                'description' => 'Change member roles and remove members',
                'resource' => 'team',
                'action' => 'manage_members',
                'category' => 'team',
            ],
            [
                'key' => 'team.manage_roles',
                'name' => 'Manage Permission Sets',
                'description' => 'Create and modify permission sets',
                'resource' => 'team',
                'action' => 'manage_roles',
                'category' => 'team',
            ],
            [
                'key' => 'team.activity',
                'name' => 'View Activity Log',
                'description' => 'Access team activity and audit logs',
                'resource' => 'team',
                'action' => 'activity',
                'category' => 'team',
            ],

            // ===================
            // SETTINGS
            // ===================
            [
                'key' => 'settings.view',
                'name' => 'View Settings',
                'description' => 'View team and project settings',
                'resource' => 'settings',
                'action' => 'view',
                'category' => 'settings',
            ],
            [
                'key' => 'settings.update',
                'name' => 'Update Settings',
                'description' => 'Modify team and project settings',
                'resource' => 'settings',
                'action' => 'update',
                'category' => 'settings',
            ],
            [
                'key' => 'settings.integrations',
                'name' => 'Manage Integrations',
                'description' => 'Connect and configure GitHub, GitLab, and other integrations',
                'resource' => 'settings',
                'action' => 'integrations',
                'category' => 'settings',
            ],
            [
                'key' => 'settings.tokens',
                'name' => 'Manage API Tokens',
                'description' => 'Create and revoke API tokens',
                'resource' => 'settings',
                'action' => 'tokens',
                'category' => 'settings',
                'is_sensitive' => true,
            ],
            [
                'key' => 'settings.notifications',
                'name' => 'Manage Notifications',
                'description' => 'Configure notification channels and alerts',
                'resource' => 'settings',
                'action' => 'notifications',
                'category' => 'settings',
            ],
            [
                'key' => 'settings.billing',
                'name' => 'Manage Billing',
                'description' => 'View and manage subscription and billing',
                'resource' => 'settings',
                'action' => 'billing',
                'category' => 'settings',
                'is_sensitive' => true,
            ],

            // ===================
            // PROJECTS
            // ===================
            [
                'key' => 'projects.view',
                'name' => 'View Projects',
                'description' => 'View projects and their details',
                'resource' => 'projects',
                'action' => 'view',
                'category' => 'resources',
            ],
            [
                'key' => 'projects.create',
                'name' => 'Create Projects',
                'description' => 'Create new projects',
                'resource' => 'projects',
                'action' => 'create',
                'category' => 'resources',
            ],
            [
                'key' => 'projects.update',
                'name' => 'Update Projects',
                'description' => 'Modify project settings',
                'resource' => 'projects',
                'action' => 'update',
                'category' => 'resources',
            ],
            [
                'key' => 'projects.delete',
                'name' => 'Delete Projects',
                'description' => 'Delete projects permanently',
                'resource' => 'projects',
                'action' => 'delete',
                'category' => 'resources',
            ],
            [
                'key' => 'projects.members',
                'name' => 'Manage Project Members',
                'description' => 'Add and remove project members',
                'resource' => 'projects',
                'action' => 'members',
                'category' => 'resources',
            ],

            // ===================
            // ENVIRONMENTS
            // ===================
            [
                'key' => 'environments.view',
                'name' => 'View Environments',
                'description' => 'View project environments',
                'resource' => 'environments',
                'action' => 'view',
                'category' => 'resources',
            ],
            [
                'key' => 'environments.create',
                'name' => 'Create Environments',
                'description' => 'Create new environments in projects',
                'resource' => 'environments',
                'action' => 'create',
                'category' => 'resources',
            ],
            [
                'key' => 'environments.update',
                'name' => 'Update Environments',
                'description' => 'Modify environment settings',
                'resource' => 'environments',
                'action' => 'update',
                'category' => 'resources',
            ],
            [
                'key' => 'environments.delete',
                'name' => 'Delete Environments',
                'description' => 'Delete environments permanently',
                'resource' => 'environments',
                'action' => 'delete',
                'category' => 'resources',
            ],
        ];
    }
}
