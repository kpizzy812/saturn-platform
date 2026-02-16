<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\SendTeamWebhookJob;
use App\Models\TeamWebhook;
use App\Models\WebhookDelivery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use OpenApi\Attributes as OA;

class TeamWebhooksController extends Controller
{
    #[OA\Get(
        summary: 'List Webhooks',
        description: 'Get all webhooks for the current team.',
        path: '/webhooks',
        operationId: 'list-webhooks',
        security: [
            ['bearerAuth' => []],
        ],
        tags: ['Webhooks'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'List of webhooks.',
                content: new OA\JsonContent(
                    properties: [
                        'data' => new OA\Property(
                            property: 'data',
                            type: 'array',
                            items: new OA\Items(type: 'object')
                        ),
                    ]
                )
            ),
            new OA\Response(
                response: 401,
                ref: '#/components/responses/401',
            ),
        ]
    )]
    public function index(): JsonResponse
    {
        $teamId = getTeamIdFromToken();
        if (is_null($teamId)) {
            return invalidTokenResponse();
        }

        $webhooks = TeamWebhook::where('team_id', $teamId)
            ->with(['deliveries' => function ($query) {
                $query->limit(5);
            }])
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function (TeamWebhook $webhook, int $key) {
                /** @var \Illuminate\Database\Eloquent\Collection<int, WebhookDelivery> $deliveries */
                $deliveries = $webhook->deliveries;

                return [
                    'id' => $webhook->id,
                    'uuid' => $webhook->uuid,
                    'name' => $webhook->name,
                    'url' => $webhook->url,
                    'events' => $webhook->events,
                    'enabled' => $webhook->enabled,
                    'created_at' => $webhook->created_at->toIso8601String(),
                    'last_triggered_at' => $webhook->last_triggered_at?->toIso8601String(),
                    'deliveries' => $deliveries->map(function (WebhookDelivery $delivery) {
                        return [
                            'id' => $delivery->id,
                            'uuid' => $delivery->uuid,
                            'event' => $delivery->event,
                            'status' => $delivery->status,
                            'status_code' => $delivery->status_code,
                            'response_time_ms' => $delivery->response_time_ms,
                            'attempts' => $delivery->attempts,
                            'created_at' => $delivery->created_at->toIso8601String(),
                        ];
                    })->all(),
                ];
            });

        return response()->json([
            'data' => $webhooks,
            'available_events' => TeamWebhook::availableEvents(),
        ]);
    }

    #[OA\Post(
        summary: 'Create Webhook',
        description: 'Create a new webhook for the current team.',
        path: '/webhooks',
        operationId: 'create-webhook',
        security: [
            ['bearerAuth' => []],
        ],
        tags: ['Webhooks'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['name', 'url', 'events'],
                properties: [
                    'name' => new OA\Property(property: 'name', type: 'string', example: 'Production Notifications'),
                    'url' => new OA\Property(property: 'url', type: 'string', format: 'url', example: 'https://api.example.com/webhook'),
                    'events' => new OA\Property(property: 'events', type: 'array', items: new OA\Items(type: 'string'), example: ['deploy.started', 'deploy.finished']),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'Webhook created successfully.',
            ),
            new OA\Response(
                response: 401,
                ref: '#/components/responses/401',
            ),
            new OA\Response(
                response: 422,
                description: 'Validation error.',
            ),
        ]
    )]
    public function store(Request $request): JsonResponse
    {
        $teamId = getTeamIdFromToken();
        if (is_null($teamId)) {
            return invalidTokenResponse();
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'url' => 'required|url|max:2048',
            'events' => 'required|array|min:1',
            'events.*' => 'string|in:'.implode(',', array_column(TeamWebhook::availableEvents(), 'value')),
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $webhook = TeamWebhook::create([
            'team_id' => $teamId,
            'name' => $request->input('name'),
            'url' => $request->input('url'),
            'events' => $request->input('events'),
            'enabled' => true,
        ]);

        return response()->json([
            'message' => 'Webhook created successfully.',
            'data' => [
                'id' => $webhook->id,
                'uuid' => $webhook->uuid,
                'name' => $webhook->name,
                'url' => $webhook->url,
                'secret' => $webhook->secret,
                'events' => $webhook->events,
                'enabled' => $webhook->enabled,
                'created_at' => $webhook->created_at->toIso8601String(),
            ],
        ], 201);
    }

    #[OA\Get(
        summary: 'Get Webhook',
        description: 'Get a specific webhook by UUID.',
        path: '/webhooks/{uuid}',
        operationId: 'get-webhook',
        security: [
            ['bearerAuth' => []],
        ],
        tags: ['Webhooks'],
        parameters: [
            new OA\Parameter(name: 'uuid', in: 'path', required: true, description: 'Webhook UUID', schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Webhook details.',
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
    public function show(string $uuid): JsonResponse
    {
        $teamId = getTeamIdFromToken();
        if (is_null($teamId)) {
            return invalidTokenResponse();
        }

        $webhook = TeamWebhook::where('team_id', $teamId)
            ->where('uuid', $uuid)
            ->first();

        if (! $webhook) {
            return response()->json(['message' => 'Webhook not found.'], 404);
        }

        return response()->json([
            'id' => $webhook->id,
            'uuid' => $webhook->uuid,
            'name' => $webhook->name,
            'url' => $webhook->url,
            'secret' => $webhook->secret,
            'events' => $webhook->events,
            'enabled' => $webhook->enabled,
            'created_at' => $webhook->created_at->toIso8601String(),
            'last_triggered_at' => $webhook->last_triggered_at?->toIso8601String(),
        ]);
    }

    #[OA\Put(
        summary: 'Update Webhook',
        description: 'Update a webhook.',
        path: '/webhooks/{uuid}',
        operationId: 'update-webhook',
        security: [
            ['bearerAuth' => []],
        ],
        tags: ['Webhooks'],
        parameters: [
            new OA\Parameter(name: 'uuid', in: 'path', required: true, description: 'Webhook UUID', schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        requestBody: new OA\RequestBody(
            content: new OA\JsonContent(
                properties: [
                    'name' => new OA\Property(property: 'name', type: 'string'),
                    'url' => new OA\Property(property: 'url', type: 'string', format: 'url'),
                    'events' => new OA\Property(property: 'events', type: 'array', items: new OA\Items(type: 'string')),
                    'enabled' => new OA\Property(property: 'enabled', type: 'boolean'),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Webhook updated successfully.',
            ),
            new OA\Response(
                response: 401,
                ref: '#/components/responses/401',
            ),
            new OA\Response(
                response: 404,
                ref: '#/components/responses/404',
            ),
            new OA\Response(
                response: 422,
                description: 'Validation error.',
            ),
        ]
    )]
    public function update(Request $request, string $uuid): JsonResponse
    {
        $teamId = getTeamIdFromToken();
        if (is_null($teamId)) {
            return invalidTokenResponse();
        }

        $webhook = TeamWebhook::where('team_id', $teamId)
            ->where('uuid', $uuid)
            ->first();

        if (! $webhook) {
            return response()->json(['message' => 'Webhook not found.'], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255',
            'url' => 'sometimes|url|max:2048',
            'events' => 'sometimes|array|min:1',
            'events.*' => 'string|in:'.implode(',', array_column(TeamWebhook::availableEvents(), 'value')),
            'enabled' => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $webhook->update($request->only(['name', 'url', 'events', 'enabled']));

        return response()->json([
            'message' => 'Webhook updated successfully.',
            'data' => [
                'id' => $webhook->id,
                'uuid' => $webhook->uuid,
                'name' => $webhook->name,
                'url' => $webhook->url,
                'events' => $webhook->events,
                'enabled' => $webhook->enabled,
                'created_at' => $webhook->created_at->toIso8601String(),
                'last_triggered_at' => $webhook->last_triggered_at?->toIso8601String(),
            ],
        ]);
    }

    #[OA\Delete(
        summary: 'Delete Webhook',
        description: 'Delete a webhook.',
        path: '/webhooks/{uuid}',
        operationId: 'delete-webhook',
        security: [
            ['bearerAuth' => []],
        ],
        tags: ['Webhooks'],
        parameters: [
            new OA\Parameter(name: 'uuid', in: 'path', required: true, description: 'Webhook UUID', schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Webhook deleted successfully.',
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
    public function destroy(string $uuid): JsonResponse
    {
        $teamId = getTeamIdFromToken();
        if (is_null($teamId)) {
            return invalidTokenResponse();
        }

        $webhook = TeamWebhook::where('team_id', $teamId)
            ->where('uuid', $uuid)
            ->first();

        if (! $webhook) {
            return response()->json(['message' => 'Webhook not found.'], 404);
        }

        $webhook->delete();

        return response()->json(['message' => 'Webhook deleted successfully.']);
    }

    #[OA\Post(
        summary: 'Toggle Webhook',
        description: 'Toggle webhook enabled/disabled status.',
        path: '/webhooks/{uuid}/toggle',
        operationId: 'toggle-webhook',
        security: [
            ['bearerAuth' => []],
        ],
        tags: ['Webhooks'],
        parameters: [
            new OA\Parameter(name: 'uuid', in: 'path', required: true, description: 'Webhook UUID', schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Webhook toggled successfully.',
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
    public function toggle(string $uuid): JsonResponse
    {
        $teamId = getTeamIdFromToken();
        if (is_null($teamId)) {
            return invalidTokenResponse();
        }

        $webhook = TeamWebhook::where('team_id', $teamId)
            ->where('uuid', $uuid)
            ->first();

        if (! $webhook) {
            return response()->json(['message' => 'Webhook not found.'], 404);
        }

        $webhook->update(['enabled' => ! $webhook->enabled]);

        return response()->json([
            'message' => $webhook->enabled ? 'Webhook enabled.' : 'Webhook disabled.',
            'enabled' => $webhook->enabled,
        ]);
    }

    #[OA\Post(
        summary: 'Test Webhook',
        description: 'Send a test delivery to the webhook.',
        path: '/webhooks/{uuid}/test',
        operationId: 'test-webhook',
        security: [
            ['bearerAuth' => []],
        ],
        tags: ['Webhooks'],
        parameters: [
            new OA\Parameter(name: 'uuid', in: 'path', required: true, description: 'Webhook UUID', schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Test webhook sent.',
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
    public function test(string $uuid): JsonResponse
    {
        $teamId = getTeamIdFromToken();
        if (is_null($teamId)) {
            return invalidTokenResponse();
        }

        $webhook = TeamWebhook::where('team_id', $teamId)
            ->where('uuid', $uuid)
            ->first();

        if (! $webhook) {
            return response()->json(['message' => 'Webhook not found.'], 404);
        }

        // Create a test delivery
        $delivery = WebhookDelivery::create([
            'team_webhook_id' => $webhook->id,
            'event' => 'test.event',
            'status' => 'pending',
            'payload' => [
                'event' => 'test.event',
                'message' => 'This is a test webhook delivery from Saturn.',
                'timestamp' => now()->toIso8601String(),
            ],
        ]);

        // Dispatch the job to send the webhook
        SendTeamWebhookJob::dispatch($webhook, $delivery);

        return response()->json([
            'message' => 'Test webhook queued for delivery.',
            'delivery_uuid' => $delivery->uuid,
        ]);
    }

    #[OA\Get(
        summary: 'Get Webhook Deliveries',
        description: 'Get delivery history for a webhook.',
        path: '/webhooks/{uuid}/deliveries',
        operationId: 'get-webhook-deliveries',
        security: [
            ['bearerAuth' => []],
        ],
        tags: ['Webhooks'],
        parameters: [
            new OA\Parameter(name: 'uuid', in: 'path', required: true, description: 'Webhook UUID', schema: new OA\Schema(type: 'string', format: 'uuid')),
            new OA\Parameter(name: 'per_page', in: 'query', required: false, description: 'Items per page', schema: new OA\Schema(type: 'integer', default: 20)),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'List of deliveries.',
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
    public function deliveries(Request $request, string $uuid): JsonResponse
    {
        $teamId = getTeamIdFromToken();
        if (is_null($teamId)) {
            return invalidTokenResponse();
        }

        $webhook = TeamWebhook::where('team_id', $teamId)
            ->where('uuid', $uuid)
            ->first();

        if (! $webhook) {
            return response()->json(['message' => 'Webhook not found.'], 404);
        }

        $perPage = min($request->integer('per_page', 20), 100);
        $deliveries = $webhook->deliveries()->paginate($perPage);

        return response()->json([
            'data' => $deliveries->items(),
            'meta' => [
                'current_page' => $deliveries->currentPage(),
                'last_page' => $deliveries->lastPage(),
                'per_page' => $deliveries->perPage(),
                'total' => $deliveries->total(),
            ],
        ]);
    }

    #[OA\Post(
        summary: 'Retry Delivery',
        description: 'Retry a failed webhook delivery.',
        path: '/webhooks/{uuid}/deliveries/{deliveryUuid}/retry',
        operationId: 'retry-webhook-delivery',
        security: [
            ['bearerAuth' => []],
        ],
        tags: ['Webhooks'],
        parameters: [
            new OA\Parameter(name: 'uuid', in: 'path', required: true, description: 'Webhook UUID', schema: new OA\Schema(type: 'string', format: 'uuid')),
            new OA\Parameter(name: 'deliveryUuid', in: 'path', required: true, description: 'Delivery UUID', schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Retry queued.',
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
    public function retryDelivery(string $uuid, string $deliveryUuid): JsonResponse
    {
        $teamId = getTeamIdFromToken();
        if (is_null($teamId)) {
            return invalidTokenResponse();
        }

        $webhook = TeamWebhook::where('team_id', $teamId)
            ->where('uuid', $uuid)
            ->first();

        if (! $webhook) {
            return response()->json(['message' => 'Webhook not found.'], 404);
        }

        $delivery = WebhookDelivery::where('team_webhook_id', $webhook->id)
            ->where('uuid', $deliveryUuid)
            ->first();

        if (! $delivery) {
            return response()->json(['message' => 'Delivery not found.'], 404);
        }

        // Reset delivery status and dispatch
        $delivery->update(['status' => 'pending']);
        SendTeamWebhookJob::dispatch($webhook, $delivery);

        return response()->json(['message' => 'Retry queued for delivery.']);
    }
}
