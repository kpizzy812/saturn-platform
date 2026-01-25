<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;
use Spatie\Activitylog\Models\Activity;

class TeamController extends Controller
{
    private function removeSensitiveData($team)
    {
        $team->makeHidden([
            'custom_server_limit',
            'pivot',
        ]);
        if (request()->attributes->get('can_read_sensitive', false) === false) {
            $team->makeHidden([
                'smtp_username',
                'smtp_password',
                'resend_api_key',
                'telegram_token',
            ]);
        }

        return serializeApiResponse($team);
    }

    #[OA\Get(
        summary: 'List',
        description: 'Get all teams.',
        path: '/teams',
        operationId: 'list-teams',
        security: [
            ['bearerAuth' => []],
        ],
        tags: ['Teams'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'List of teams.',
                content: [
                    new OA\MediaType(
                        mediaType: 'application/json',
                        schema: new OA\Schema(
                            type: 'array',
                            items: new OA\Items(ref: '#/components/schemas/Team')
                        )
                    ),
                ]),
            new OA\Response(
                response: 401,
                ref: '#/components/responses/401',
            ),
            new OA\Response(
                response: 400,
                ref: '#/components/responses/400',
            ),
        ]
    )]
    public function teams(Request $request)
    {
        $teamId = getTeamIdFromToken();
        if (is_null($teamId)) {
            return invalidTokenResponse();
        }
        $teams = auth()->user()->teams->sortBy('id');
        $teams = $teams->map(function ($team) {
            return $this->removeSensitiveData($team);
        });

        return response()->json(
            $teams,
        );
    }

    #[OA\Get(
        summary: 'Get',
        description: 'Get team by TeamId.',
        path: '/teams/{id}',
        operationId: 'get-team-by-id',
        security: [
            ['bearerAuth' => []],
        ],
        tags: ['Teams'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, description: 'Team ID', schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'List of teams.',
                content: new OA\JsonContent(ref: '#/components/schemas/Team')
            ),
            new OA\Response(
                response: 401,
                ref: '#/components/responses/401',
            ),
            new OA\Response(
                response: 400,
                ref: '#/components/responses/400',
            ),
            new OA\Response(
                response: 404,
                ref: '#/components/responses/404',
            ),
        ]
    )]
    public function team_by_id(Request $request)
    {
        $id = $request->id;
        $teamId = getTeamIdFromToken();
        if (is_null($teamId)) {
            return invalidTokenResponse();
        }
        $teams = auth()->user()->teams;
        $team = $teams->where('id', $id)->first();
        if (is_null($team)) {
            return response()->json(['message' => 'Team not found.'], 404);
        }
        $team = $this->removeSensitiveData($team);

        return response()->json(
            serializeApiResponse($team),
        );
    }

    #[OA\Get(
        summary: 'Members',
        description: 'Get members by TeamId.',
        path: '/teams/{id}/members',
        operationId: 'get-members-by-team-id',
        security: [
            ['bearerAuth' => []],
        ],
        tags: ['Teams'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, description: 'Team ID', schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'List of members.',
                content: [
                    new OA\MediaType(
                        mediaType: 'application/json',
                        schema: new OA\Schema(
                            type: 'array',
                            items: new OA\Items(ref: '#/components/schemas/User')
                        )
                    ),
                ]),
            new OA\Response(
                response: 401,
                ref: '#/components/responses/401',
            ),
            new OA\Response(
                response: 400,
                ref: '#/components/responses/400',
            ),
            new OA\Response(
                response: 404,
                ref: '#/components/responses/404',
            ),
        ]
    )]
    public function members_by_id(Request $request)
    {
        $id = $request->id;
        $teamId = getTeamIdFromToken();
        if (is_null($teamId)) {
            return invalidTokenResponse();
        }
        $teams = auth()->user()->teams;
        $team = $teams->where('id', $id)->first();
        if (is_null($team)) {
            return response()->json(['message' => 'Team not found.'], 404);
        }
        $members = $team->members;
        $members->makeHidden([
            'pivot',
            'email_change_code',
            'email_change_code_expires_at',
        ]);

        return response()->json(
            serializeApiResponse($members),
        );
    }

    #[OA\Get(
        summary: 'Authenticated Team',
        description: 'Get currently authenticated team.',
        path: '/teams/current',
        operationId: 'get-current-team',
        security: [
            ['bearerAuth' => []],
        ],
        tags: ['Teams'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Current Team.',
                content: new OA\JsonContent(ref: '#/components/schemas/Team')),
            new OA\Response(
                response: 401,
                ref: '#/components/responses/401',
            ),
            new OA\Response(
                response: 400,
                ref: '#/components/responses/400',
            ),
        ]
    )]
    public function current_team(Request $request)
    {
        $teamId = getTeamIdFromToken();
        if (is_null($teamId)) {
            return invalidTokenResponse();
        }
        $team = auth()->user()->currentTeam();

        return response()->json(
            $this->removeSensitiveData($team),
        );
    }

    #[OA\Get(
        summary: 'Authenticated Team Members',
        description: 'Get currently authenticated team members.',
        path: '/teams/current/members',
        operationId: 'get-current-team-members',
        security: [
            ['bearerAuth' => []],
        ],
        tags: ['Teams'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Currently authenticated team members.',
                content: [
                    new OA\MediaType(
                        mediaType: 'application/json',
                        schema: new OA\Schema(
                            type: 'array',
                            items: new OA\Items(ref: '#/components/schemas/User')
                        )
                    ),
                ]),
            new OA\Response(
                response: 401,
                ref: '#/components/responses/401',
            ),
            new OA\Response(
                response: 400,
                ref: '#/components/responses/400',
            ),
        ]
    )]
    public function current_team_members(Request $request)
    {
        $teamId = getTeamIdFromToken();
        if (is_null($teamId)) {
            return invalidTokenResponse();
        }
        $team = auth()->user()->currentTeam();
        $team->members->makeHidden([
            'pivot',
            'email_change_code',
            'email_change_code_expires_at',
        ]);

        return response()->json(
            serializeApiResponse($team->members),
        );
    }

    #[OA\Get(
        summary: 'Authenticated Team Activities',
        description: 'Get activity log for currently authenticated team.',
        path: '/teams/current/activities',
        operationId: 'get-current-team-activities',
        security: [
            ['bearerAuth' => []],
        ],
        tags: ['Teams'],
        parameters: [
            new OA\Parameter(
                name: 'action',
                in: 'query',
                required: false,
                description: 'Filter by action type',
                schema: new OA\Schema(type: 'string')
            ),
            new OA\Parameter(
                name: 'member',
                in: 'query',
                required: false,
                description: 'Filter by member email',
                schema: new OA\Schema(type: 'string')
            ),
            new OA\Parameter(
                name: 'date_range',
                in: 'query',
                required: false,
                description: 'Filter by date range (today, yesterday, week, month)',
                schema: new OA\Schema(type: 'string')
            ),
            new OA\Parameter(
                name: 'search',
                in: 'query',
                required: false,
                description: 'Search in activity descriptions',
                schema: new OA\Schema(type: 'string')
            ),
            new OA\Parameter(
                name: 'per_page',
                in: 'query',
                required: false,
                description: 'Number of items per page (default: 50)',
                schema: new OA\Schema(type: 'integer')
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Team activity log.',
                content: [
                    new OA\MediaType(
                        mediaType: 'application/json',
                        schema: new OA\Schema(
                            type: 'object',
                            properties: [
                                new OA\Property(
                                    property: 'data',
                                    type: 'array',
                                    items: new OA\Items(
                                        type: 'object',
                                        properties: [
                                            new OA\Property(property: 'id', type: 'string'),
                                            new OA\Property(property: 'action', type: 'string'),
                                            new OA\Property(property: 'description', type: 'string'),
                                            new OA\Property(
                                                property: 'user',
                                                type: 'object',
                                                properties: [
                                                    new OA\Property(property: 'name', type: 'string'),
                                                    new OA\Property(property: 'email', type: 'string'),
                                                    new OA\Property(property: 'avatar', type: 'string', nullable: true),
                                                ]
                                            ),
                                            new OA\Property(
                                                property: 'resource',
                                                type: 'object',
                                                nullable: true,
                                                properties: [
                                                    new OA\Property(property: 'type', type: 'string'),
                                                    new OA\Property(property: 'name', type: 'string'),
                                                    new OA\Property(property: 'id', type: 'string'),
                                                ]
                                            ),
                                            new OA\Property(property: 'timestamp', type: 'string', format: 'date-time'),
                                        ]
                                    )
                                ),
                                new OA\Property(property: 'meta', type: 'object'),
                            ]
                        )
                    ),
                ]
            ),
            new OA\Response(
                response: 401,
                ref: '#/components/responses/401',
            ),
            new OA\Response(
                response: 400,
                ref: '#/components/responses/400',
            ),
        ]
    )]
    public function current_team_activities(Request $request)
    {
        $teamId = getTeamIdFromToken();
        if (is_null($teamId)) {
            return invalidTokenResponse();
        }

        $team = auth()->user()->currentTeam();
        $memberIds = $team->members->pluck('id')->toArray();

        $perPage = min((int) $request->get('per_page', 50), 100);

        $query = Activity::query()
            ->where('causer_type', 'App\\Models\\User')
            ->whereIn('causer_id', $memberIds)
            ->with('causer', 'subject')
            ->orderBy('created_at', 'desc');

        // Filter by action/event type
        if ($request->has('action') && $request->action !== 'all') {
            $query->where('event', $request->action);
        }

        // Filter by member
        if ($request->has('member') && $request->member !== 'all') {
            $memberUser = $team->members->firstWhere('email', $request->member);
            if ($memberUser) {
                $query->where('causer_id', $memberUser->id);
            }
        }

        // Filter by date range
        if ($request->has('date_range') && $request->date_range !== 'all') {
            $now = now();
            $query->where(function ($q) use ($request, $now) {
                switch ($request->date_range) {
                    case 'today':
                        $q->whereDate('created_at', $now->toDateString());
                        break;
                    case 'yesterday':
                        $q->whereDate('created_at', $now->subDay()->toDateString());
                        break;
                    case 'week':
                        $q->where('created_at', '>=', $now->subDays(7));
                        break;
                    case 'month':
                        $q->where('created_at', '>=', $now->subDays(30));
                        break;
                }
            });
        }

        // Search filter
        if ($request->has('search') && ! empty($request->search)) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('description', 'like', "%{$search}%")
                    ->orWhere('log_name', 'like', "%{$search}%");
            });
        }

        $activities = $query->paginate($perPage);

        // Transform activities to expected format
        $transformed = $activities->map(function ($activity) {
            $causer = $activity->causer;
            $subject = $activity->subject;

            // Determine resource type from subject
            $resourceType = null;
            $resourceName = null;
            $resourceId = null;

            if ($subject) {
                $resourceType = match (class_basename($subject)) {
                    'Application' => 'application',
                    'Service' => 'service',
                    'StandalonePostgresql', 'StandaloneMysql', 'StandaloneMongodb', 'StandaloneRedis',
                    'StandaloneMariadb', 'StandaloneKeydb', 'StandaloneDragonfly', 'StandaloneClickhouse' => 'database',
                    'Server' => 'server',
                    'Team' => 'team',
                    'Project' => 'project',
                    'Environment' => 'environment',
                    default => strtolower(class_basename($subject)),
                };
                $resourceName = $subject->name ?? $subject->uuid ?? 'Unknown';
                $resourceId = (string) $subject->id;
            }

            // Map event to action type
            $action = $activity->event ?? $activity->log_name ?? 'unknown';
            $actionMap = [
                'created' => $this->mapCreatedAction($subject),
                'updated' => $this->mapUpdatedAction($subject),
                'deleted' => $this->mapDeletedAction($subject),
                'deployed' => 'deployment_started',
                'deployment_completed' => 'deployment_completed',
                'deployment_failed' => 'deployment_failed',
                'started' => 'application_started',
                'stopped' => 'application_stopped',
                'restarted' => 'application_restarted',
            ];
            $mappedAction = $actionMap[$action] ?? $action;

            return [
                'id' => (string) $activity->id,
                'action' => $mappedAction,
                'description' => $activity->description,
                'user' => [
                    'name' => $causer?->name ?? 'System',
                    'email' => $causer?->email ?? 'system@saturn.local',
                    'avatar' => null,
                ],
                'resource' => $resourceType ? [
                    'type' => $resourceType,
                    'name' => $resourceName,
                    'id' => $resourceId,
                ] : null,
                'timestamp' => $activity->created_at->toIso8601String(),
            ];
        });

        return response()->json([
            'data' => $transformed,
            'meta' => [
                'current_page' => $activities->currentPage(),
                'last_page' => $activities->lastPage(),
                'per_page' => $activities->perPage(),
                'total' => $activities->total(),
            ],
        ]);
    }

    /**
     * Map 'created' event to specific action based on subject type
     */
    private function mapCreatedAction($subject): string
    {
        if (! $subject) {
            return 'settings_updated';
        }

        return match (class_basename($subject)) {
            'Application', 'Service' => 'deployment_started',
            'StandalonePostgresql', 'StandaloneMysql', 'StandaloneMongodb', 'StandaloneRedis',
            'StandaloneMariadb', 'StandaloneKeydb', 'StandaloneDragonfly', 'StandaloneClickhouse' => 'database_created',
            'Server' => 'server_connected',
            'Team' => 'team_member_added',
            default => 'settings_updated',
        };
    }

    /**
     * Map 'updated' event to specific action based on subject type
     */
    private function mapUpdatedAction($subject): string
    {
        if (! $subject) {
            return 'settings_updated';
        }

        return match (class_basename($subject)) {
            'EnvironmentVariable' => 'environment_variable_updated',
            default => 'settings_updated',
        };
    }

    /**
     * Map 'deleted' event to specific action based on subject type
     */
    private function mapDeletedAction($subject): string
    {
        if (! $subject) {
            return 'settings_updated';
        }

        return match (class_basename($subject)) {
            'StandalonePostgresql', 'StandaloneMysql', 'StandaloneMongodb', 'StandaloneRedis',
            'StandaloneMariadb', 'StandaloneKeydb', 'StandaloneDragonfly', 'StandaloneClickhouse' => 'database_deleted',
            'Server' => 'server_disconnected',
            'Team' => 'team_member_removed',
            default => 'settings_updated',
        };
    }
}
