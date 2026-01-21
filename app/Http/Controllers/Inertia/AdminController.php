<?php

namespace App\Http\Controllers\Inertia;

use App\Enums\ApplicationDeploymentStatus;
use App\Http\Controllers\Controller;
use App\Models\Application;
use App\Models\ApplicationDeploymentQueue;
use App\Models\AuditLog;
use App\Models\InstanceSettings;
use App\Models\Server;
use App\Models\Service;
use App\Models\StandaloneClickhouse;
use App\Models\StandaloneDragonfly;
use App\Models\StandaloneKeydb;
use App\Models\StandaloneMariadb;
use App\Models\StandaloneMongodb;
use App\Models\StandaloneMysql;
use App\Models\StandalonePostgresql;
use App\Models\StandaloneRedis;
use App\Models\Team;
use App\Models\User;
use Inertia\Inertia;
use Inertia\Response;

class AdminController extends Controller
{
    /**
     * Display admin dashboard.
     */
    public function index(): Response
    {
        $stats = [
            'totalUsers' => User::count(),
            'activeUsers' => User::whereNotNull('email_verified_at')->count(),
            'totalServers' => Server::count(),
            'totalDeployments' => ApplicationDeploymentQueue::count(),
            'failedDeployments' => ApplicationDeploymentQueue::where('status', ApplicationDeploymentStatus::FAILED->value)->count(),
            'totalTeams' => Team::count(),
            'totalApplications' => Application::count(),
            'totalDatabases' => $this->getTotalDatabasesCount(),
            'totalServices' => Service::count(),
        ];

        // Get recent activity from audit logs
        $recentActivity = AuditLog::with(['user', 'team'])
            ->latest()
            ->limit(10)
            ->get()
            ->map(function ($log) {
                return [
                    'id' => $log->id,
                    'action' => $log->action,
                    'description' => $log->description,
                    'user_name' => $log->user?->name,
                    'team_name' => $log->team?->name,
                    'resource_type' => $log->resource_type_name,
                    'resource_name' => $log->resource_name,
                    'created_at' => $log->created_at,
                ];
            });

        return Inertia::render('Admin/Index', [
            'stats' => $stats,
            'recentActivity' => $recentActivity,
        ]);
    }

    /**
     * Display all users.
     */
    public function users(): Response
    {
        $users = User::with('teams')
            ->withCount('teams')
            ->orderBy('created_at', 'desc')
            ->paginate(50)
            ->through(function ($user) {
                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'email_verified_at' => $user->email_verified_at,
                    'created_at' => $user->created_at,
                    'updated_at' => $user->updated_at,
                    'teams_count' => $user->teams_count,
                    'is_superadmin' => $user->is_superadmin,
                ];
            });

        return Inertia::render('Admin/Users/Index', [
            'users' => $users,
        ]);
    }

    /**
     * Display specific user.
     */
    public function showUser(int $id): Response
    {
        $user = User::with(['teams' => function ($query) {
            $query->withPivot('role');
        }])->findOrFail($id);

        return Inertia::render('Admin/Users/Show', [
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'email_verified_at' => $user->email_verified_at,
                'created_at' => $user->created_at,
                'updated_at' => $user->updated_at,
                'is_superadmin' => $user->is_superadmin,
                'force_password_reset' => $user->force_password_reset,
                'teams' => $user->teams->map(function ($team) {
                    return [
                        'id' => $team->id,
                        'name' => $team->name,
                        'personal_team' => $team->personal_team,
                        'role' => $team->pivot->role,
                    ];
                }),
            ],
        ]);
    }

    /**
     * Display all applications (admin view).
     */
    public function applications(): Response
    {
        $applications = Application::with(['environment.project.team', 'destination'])
            ->orderBy('updated_at', 'desc')
            ->paginate(50)
            ->through(function ($app) {
                return [
                    'id' => $app->id,
                    'uuid' => $app->uuid,
                    'name' => $app->name,
                    'description' => $app->description,
                    'status' => $app->status,
                    'git_repository' => $app->git_repository,
                    'git_branch' => $app->git_branch,
                    'build_pack' => $app->build_pack,
                    'team_id' => $app->environment->project->team->id ?? null,
                    'team_name' => $app->environment->project->team->name ?? null,
                    'environment_id' => $app->environment_id,
                    'environment_name' => $app->environment->name ?? null,
                    'project_name' => $app->environment->project->name ?? null,
                    'created_at' => $app->created_at,
                    'updated_at' => $app->updated_at,
                ];
            });

        return Inertia::render('Admin/Applications/Index', [
            'applications' => $applications,
        ]);
    }

    /**
     * Display all databases (admin view).
     */
    public function databases(): Response
    {
        $databases = $this->getAllDatabases();

        // Paginate manually since we're combining multiple collections
        $page = request()->get('page', 1);
        $perPage = 50;
        $offset = ($page - 1) * $perPage;

        $paginatedDatabases = $databases->slice($offset, $perPage)->values();
        $total = $databases->count();

        return Inertia::render('Admin/Databases/Index', [
            'databases' => [
                'data' => $paginatedDatabases,
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'last_page' => ceil($total / $perPage),
            ],
        ]);
    }

    /**
     * Display all services (admin view).
     */
    public function services(): Response
    {
        $services = Service::with(['environment.project.team', 'destination'])
            ->orderBy('updated_at', 'desc')
            ->paginate(50)
            ->through(function ($service) {
                return [
                    'id' => $service->id,
                    'uuid' => $service->uuid,
                    'name' => $service->name,
                    'description' => $service->description,
                    'status' => $service->status,
                    'service_type' => $service->service_type,
                    'team_id' => $service->environment->project->team->id ?? null,
                    'team_name' => $service->environment->project->team->name ?? null,
                    'environment_id' => $service->environment_id,
                    'environment_name' => $service->environment->name ?? null,
                    'project_name' => $service->environment->project->name ?? null,
                    'created_at' => $service->created_at,
                    'updated_at' => $service->updated_at,
                ];
            });

        return Inertia::render('Admin/Services/Index', [
            'services' => $services,
        ]);
    }

    /**
     * Display all servers (admin view).
     */
    public function servers(): Response
    {
        $servers = Server::with('team')
            ->orderBy('updated_at', 'desc')
            ->paginate(50)
            ->through(function ($server) {
                return [
                    'id' => $server->id,
                    'uuid' => $server->uuid,
                    'name' => $server->name,
                    'description' => $server->description,
                    'ip' => $server->ip,
                    'is_reachable' => $server->is_reachable,
                    'is_build_server' => $server->is_build_server,
                    'team_id' => $server->team_id,
                    'team_name' => $server->team->name ?? null,
                    'created_at' => $server->created_at,
                    'updated_at' => $server->updated_at,
                ];
            });

        return Inertia::render('Admin/Servers/Index', [
            'servers' => $servers,
        ]);
    }

    /**
     * Display all deployments (admin view).
     */
    public function deployments(): Response
    {
        $deployments = ApplicationDeploymentQueue::with(['application.environment.project.team'])
            ->orderBy('created_at', 'desc')
            ->paginate(50)
            ->through(function ($deployment) {
                return [
                    'id' => $deployment->id,
                    'deployment_uuid' => $deployment->deployment_uuid,
                    'application_id' => $deployment->application_id,
                    'application_name' => $deployment->application->name ?? null,
                    'status' => $deployment->status,
                    'commit' => $deployment->commit,
                    'commit_message' => $deployment->commit_message,
                    'is_webhook' => $deployment->is_webhook,
                    'is_api' => $deployment->is_api,
                    'team_id' => $deployment->application->environment->project->team->id ?? null,
                    'team_name' => $deployment->application->environment->project->team->name ?? null,
                    'created_at' => $deployment->created_at,
                    'updated_at' => $deployment->updated_at,
                ];
            });

        return Inertia::render('Admin/Deployments/Index', [
            'deployments' => $deployments,
        ]);
    }

    /**
     * Display all teams (admin view).
     */
    public function teams(): Response
    {
        $teams = Team::withCount(['members', 'servers'])
            ->orderBy('created_at', 'desc')
            ->paginate(50)
            ->through(function ($team) {
                return [
                    'id' => $team->id,
                    'name' => $team->name,
                    'description' => $team->description,
                    'personal_team' => $team->personal_team,
                    'members_count' => $team->members_count,
                    'servers_count' => $team->servers_count,
                    'created_at' => $team->created_at,
                    'updated_at' => $team->updated_at,
                ];
            });

        return Inertia::render('Admin/Teams/Index', [
            'teams' => $teams,
        ]);
    }

    /**
     * Display admin settings.
     */
    public function settings(): Response
    {
        $settings = InstanceSettings::get();

        return Inertia::render('Admin/Settings/Index', [
            'settings' => [
                'id' => $settings->id,
                'fqdn' => $settings->fqdn,
                'instance_name' => $settings->instance_name,
                'allowed_ip_ranges' => $settings->allowed_ip_ranges,
                'is_auto_update_enabled' => $settings->is_auto_update_enabled,
                'auto_update_frequency' => $settings->auto_update_frequency,
                'update_check_frequency' => $settings->update_check_frequency,
                'is_wire_navigate_enabled' => $settings->is_wire_navigate_enabled,
                'smtp_enabled' => $settings->smtp_enabled,
                'smtp_host' => $settings->smtp_host ? '***' : null, // Mask sensitive data
                'smtp_port' => $settings->smtp_port,
                'smtp_from_address' => $settings->smtp_from_address ? '***' : null, // Mask sensitive data
                'resend_enabled' => $settings->resend_enabled,
                'created_at' => $settings->created_at,
                'updated_at' => $settings->updated_at,
            ],
        ]);
    }

    /**
     * Display system logs.
     */
    public function logs(): Response
    {
        $logs = AuditLog::with(['user', 'team'])
            ->orderBy('created_at', 'desc')
            ->paginate(100)
            ->through(function ($log) {
                return [
                    'id' => $log->id,
                    'user_id' => $log->user_id,
                    'user_name' => $log->user?->name,
                    'user_email' => $log->user?->email,
                    'team_id' => $log->team_id,
                    'team_name' => $log->team?->name,
                    'action' => $log->action,
                    'formatted_action' => $log->formatted_action,
                    'resource_type' => $log->resource_type,
                    'resource_type_name' => $log->resource_type_name,
                    'resource_id' => $log->resource_id,
                    'resource_name' => $log->resource_name,
                    'description' => $log->description,
                    'metadata' => $log->metadata,
                    'ip_address' => $log->ip_address,
                    'user_agent' => $log->user_agent,
                    'created_at' => $log->created_at,
                ];
            });

        return Inertia::render('Admin/Logs/Index', [
            'logs' => $logs,
        ]);
    }

    /**
     * Get all databases across all types (admin view - bypasses team scoping).
     */
    protected function getAllDatabases()
    {
        $databases = StandalonePostgresql::with('environment.project.team')
            ->orderBy('updated_at', 'desc')
            ->get()
            ->map(fn ($db) => $this->formatDatabaseForAdminView($db, 'postgresql'));

        $mysqlDatabases = StandaloneMysql::with('environment.project.team')
            ->orderBy('updated_at', 'desc')
            ->get()
            ->map(fn ($db) => $this->formatDatabaseForAdminView($db, 'mysql'));

        $mariadbDatabases = StandaloneMariadb::with('environment.project.team')
            ->orderBy('updated_at', 'desc')
            ->get()
            ->map(fn ($db) => $this->formatDatabaseForAdminView($db, 'mariadb'));

        $mongodbDatabases = StandaloneMongodb::with('environment.project.team')
            ->orderBy('updated_at', 'desc')
            ->get()
            ->map(fn ($db) => $this->formatDatabaseForAdminView($db, 'mongodb'));

        $redisDatabases = StandaloneRedis::with('environment.project.team')
            ->orderBy('updated_at', 'desc')
            ->get()
            ->map(fn ($db) => $this->formatDatabaseForAdminView($db, 'redis'));

        $clickhouseDatabases = StandaloneClickhouse::with('environment.project.team')
            ->orderBy('updated_at', 'desc')
            ->get()
            ->map(fn ($db) => $this->formatDatabaseForAdminView($db, 'clickhouse'));

        $dragonflyDatabases = StandaloneDragonfly::with('environment.project.team')
            ->orderBy('updated_at', 'desc')
            ->get()
            ->map(fn ($db) => $this->formatDatabaseForAdminView($db, 'dragonfly'));

        $keydbDatabases = StandaloneKeydb::with('environment.project.team')
            ->orderBy('updated_at', 'desc')
            ->get()
            ->map(fn ($db) => $this->formatDatabaseForAdminView($db, 'keydb'));

        return $databases
            ->concat($mysqlDatabases)
            ->concat($mariadbDatabases)
            ->concat($mongodbDatabases)
            ->concat($redisDatabases)
            ->concat($clickhouseDatabases)
            ->concat($dragonflyDatabases)
            ->concat($keydbDatabases)
            ->sortByDesc('updated_at')
            ->values();
    }

    /**
     * Format database data for admin view.
     */
    protected function formatDatabaseForAdminView($database, string $type): array
    {
        return [
            'id' => $database->id,
            'uuid' => $database->uuid,
            'name' => $database->name,
            'description' => $database->description,
            'database_type' => $type,
            'status' => $database->status,
            'team_id' => $database->environment->project->team->id ?? null,
            'team_name' => $database->environment->project->team->name ?? null,
            'environment_id' => $database->environment_id,
            'environment_name' => $database->environment->name ?? null,
            'project_name' => $database->environment->project->name ?? null,
            'created_at' => $database->created_at,
            'updated_at' => $database->updated_at,
        ];
    }

    /**
     * Get total count of all database types.
     */
    protected function getTotalDatabasesCount(): int
    {
        return StandalonePostgresql::count()
            + StandaloneMysql::count()
            + StandaloneMariadb::count()
            + StandaloneMongodb::count()
            + StandaloneRedis::count()
            + StandaloneClickhouse::count()
            + StandaloneDragonfly::count()
            + StandaloneKeydb::count();
    }
}
