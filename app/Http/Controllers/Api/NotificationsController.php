<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\UserNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class NotificationsController extends Controller
{
    #[OA\Get(
        summary: 'List',
        description: 'Get all notifications for the current team.',
        path: '/notifications',
        operationId: 'list-notifications',
        security: [
            ['bearerAuth' => []],
        ],
        tags: ['Notifications'],
        parameters: [
            new OA\Parameter(name: 'page', in: 'query', required: false, description: 'Page number', schema: new OA\Schema(type: 'integer', default: 1)),
            new OA\Parameter(name: 'per_page', in: 'query', required: false, description: 'Items per page', schema: new OA\Schema(type: 'integer', default: 20)),
            new OA\Parameter(name: 'type', in: 'query', required: false, description: 'Filter by notification type', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'is_read', in: 'query', required: false, description: 'Filter by read status', schema: new OA\Schema(type: 'boolean')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'List of notifications.',
                content: [
                    new OA\MediaType(
                        mediaType: 'application/json',
                        schema: new OA\Schema(
                            type: 'object',
                            properties: [
                                'data' => new OA\Property(
                                    property: 'data',
                                    type: 'array',
                                    items: new OA\Items(ref: '#/components/schemas/UserNotification')
                                ),
                                'meta' => new OA\Property(
                                    property: 'meta',
                                    type: 'object',
                                    properties: [
                                        'total' => new OA\Property(property: 'total', type: 'integer'),
                                        'unread_count' => new OA\Property(property: 'unread_count', type: 'integer'),
                                    ]
                                ),
                            ]
                        )
                    ),
                ]
            ),
            new OA\Response(
                response: 401,
                ref: '#/components/responses/401',
            ),
        ]
    )]
    public function index(Request $request): JsonResponse
    {
        $teamId = getTeamIdFromToken();
        if (is_null($teamId)) {
            return invalidTokenResponse();
        }

        $perPage = min($request->integer('per_page', 20), 100);

        $query = UserNotification::where('team_id', $teamId)
            ->orderBy('created_at', 'desc');

        // Filter by type
        if ($request->filled('type')) {
            $query->where('type', $request->input('type'));
        }

        // Filter by read status
        if ($request->has('is_read')) {
            $query->where('is_read', filter_var($request->input('is_read'), FILTER_VALIDATE_BOOLEAN));
        }

        $notifications = $query->paginate($perPage);

        // Get unread count for the team
        $unreadCount = UserNotification::where('team_id', $teamId)
            ->where('is_read', false)
            ->count();

        return response()->json([
            'data' => $notifications->items(),
            'meta' => [
                'current_page' => $notifications->currentPage(),
                'last_page' => $notifications->lastPage(),
                'per_page' => $notifications->perPage(),
                'total' => $notifications->total(),
                'unread_count' => $unreadCount,
            ],
        ]);
    }

    #[OA\Get(
        summary: 'Get',
        description: 'Get a specific notification by ID.',
        path: '/notifications/{id}',
        operationId: 'get-notification',
        security: [
            ['bearerAuth' => []],
        ],
        tags: ['Notifications'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, description: 'Notification UUID', schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Notification details.',
                content: new OA\JsonContent(ref: '#/components/schemas/UserNotification')
            ),
            new OA\Response(
                response: 401,
                ref: '#/components/responses/401',
            ),
            new OA\Response(
                response: 404,
                ref: '#/components/responses/404',
            ),
        ]
    )]
    public function show(Request $request, string $id): JsonResponse
    {
        $teamId = getTeamIdFromToken();
        if (is_null($teamId)) {
            return invalidTokenResponse();
        }

        $notification = UserNotification::where('team_id', $teamId)
            ->where('id', $id)
            ->first();

        if (! $notification) {
            return response()->json(['message' => 'Notification not found.'], 404);
        }

        return response()->json($notification);
    }

    #[OA\Post(
        summary: 'Mark as Read',
        description: 'Mark a notification as read.',
        path: '/notifications/{id}/read',
        operationId: 'mark-notification-read',
        security: [
            ['bearerAuth' => []],
        ],
        tags: ['Notifications'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, description: 'Notification UUID', schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Notification marked as read.',
                content: new OA\JsonContent(
                    properties: [
                        'message' => new OA\Property(property: 'message', type: 'string', example: 'Notification marked as read.'),
                    ]
                )
            ),
            new OA\Response(
                response: 401,
                ref: '#/components/responses/401',
            ),
            new OA\Response(
                response: 404,
                ref: '#/components/responses/404',
            ),
        ]
    )]
    public function markAsRead(Request $request, string $id): JsonResponse
    {
        $teamId = getTeamIdFromToken();
        if (is_null($teamId)) {
            return invalidTokenResponse();
        }

        $notification = UserNotification::where('team_id', $teamId)
            ->where('id', $id)
            ->first();

        if (! $notification) {
            return response()->json(['message' => 'Notification not found.'], 404);
        }

        $notification->markAsRead();

        return response()->json(['message' => 'Notification marked as read.']);
    }

    #[OA\Post(
        summary: 'Mark All as Read',
        description: 'Mark all notifications as read for the current team.',
        path: '/notifications/read-all',
        operationId: 'mark-all-notifications-read',
        security: [
            ['bearerAuth' => []],
        ],
        tags: ['Notifications'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'All notifications marked as read.',
                content: new OA\JsonContent(
                    properties: [
                        'message' => new OA\Property(property: 'message', type: 'string', example: 'All notifications marked as read.'),
                        'count' => new OA\Property(property: 'count', type: 'integer', example: 5),
                    ]
                )
            ),
            new OA\Response(
                response: 401,
                ref: '#/components/responses/401',
            ),
        ]
    )]
    public function markAllAsRead(Request $request): JsonResponse
    {
        $teamId = getTeamIdFromToken();
        if (is_null($teamId)) {
            return invalidTokenResponse();
        }

        $count = UserNotification::where('team_id', $teamId)
            ->where('is_read', false)
            ->update([
                'is_read' => true,
                'read_at' => now(),
            ]);

        return response()->json([
            'message' => 'All notifications marked as read.',
            'count' => $count,
        ]);
    }

    #[OA\Delete(
        summary: 'Delete',
        description: 'Delete a notification.',
        path: '/notifications/{id}',
        operationId: 'delete-notification',
        security: [
            ['bearerAuth' => []],
        ],
        tags: ['Notifications'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, description: 'Notification UUID', schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Notification deleted.',
                content: new OA\JsonContent(
                    properties: [
                        'message' => new OA\Property(property: 'message', type: 'string', example: 'Notification deleted.'),
                    ]
                )
            ),
            new OA\Response(
                response: 401,
                ref: '#/components/responses/401',
            ),
            new OA\Response(
                response: 404,
                ref: '#/components/responses/404',
            ),
        ]
    )]
    public function destroy(Request $request, string $id): JsonResponse
    {
        $teamId = getTeamIdFromToken();
        if (is_null($teamId)) {
            return invalidTokenResponse();
        }

        $notification = UserNotification::where('team_id', $teamId)
            ->where('id', $id)
            ->first();

        if (! $notification) {
            return response()->json(['message' => 'Notification not found.'], 404);
        }

        $notification->delete();

        return response()->json(['message' => 'Notification deleted.']);
    }

    #[OA\Get(
        summary: 'Unread Count',
        description: 'Get the count of unread notifications.',
        path: '/notifications/unread-count',
        operationId: 'get-unread-notifications-count',
        security: [
            ['bearerAuth' => []],
        ],
        tags: ['Notifications'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Unread notification count.',
                content: new OA\JsonContent(
                    properties: [
                        'count' => new OA\Property(property: 'count', type: 'integer', example: 5),
                    ]
                )
            ),
            new OA\Response(
                response: 401,
                ref: '#/components/responses/401',
            ),
        ]
    )]
    public function unreadCount(Request $request): JsonResponse
    {
        $teamId = getTeamIdFromToken();
        if (is_null($teamId)) {
            return invalidTokenResponse();
        }

        $count = UserNotification::where('team_id', $teamId)
            ->where('is_read', false)
            ->count();

        return response()->json(['count' => $count]);
    }
}
