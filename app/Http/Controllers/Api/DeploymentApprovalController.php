<?php

namespace App\Http\Controllers\Api;

use App\Actions\Deployment\ApproveDeploymentAction;
use App\Actions\Deployment\RequestDeploymentApprovalAction;
use App\Http\Controllers\Controller;
use App\Models\ApplicationDeploymentQueue;
use App\Models\DeploymentApproval;
use App\Models\Project;
use App\Models\User;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class DeploymentApprovalController extends Controller
{
    public function __construct(
        private RequestDeploymentApprovalAction $requestApprovalAction,
        private ApproveDeploymentAction $approveAction
    ) {}

    #[OA\Post(
        summary: 'Request deployment approval',
        description: 'Request approval for a deployment to a protected environment.',
        path: '/deployments/{uuid}/request-approval',
        operationId: 'request-deployment-approval',
        security: [
            ['bearerAuth' => []],
        ],
        tags: ['Deployment Approvals'],
        parameters: [
            new OA\Parameter(name: 'uuid', in: 'path', required: true, description: 'Deployment UUID', schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Approval request created or already exists.',
                content: [
                    new OA\MediaType(
                        mediaType: 'application/json',
                        schema: new OA\Schema(
                            type: 'object',
                            properties: [
                                'message' => ['type' => 'string'],
                                'approval_uuid' => ['type' => 'string'],
                                'status' => ['type' => 'string'],
                            ]
                        )
                    ),
                ]),
            new OA\Response(response: 400, ref: '#/components/responses/400'),
            new OA\Response(response: 401, ref: '#/components/responses/401'),
            new OA\Response(response: 404, ref: '#/components/responses/404'),
        ]
    )]
    public function requestApproval(Request $request, string $uuid)
    {
        $teamId = getTeamIdFromToken();
        if (is_null($teamId)) {
            return invalidTokenResponse();
        }

        $deployment = ApplicationDeploymentQueue::where('deployment_uuid', $uuid)->first();
        if (! $deployment) {
            return response()->json(['message' => 'Deployment not found.'], 404);
        }

        // Check if deployment belongs to team
        $application = $deployment->application;
        if (! $application || $application->team()?->id !== (int) $teamId) {
            return response()->json(['message' => 'Deployment not found.'], 404);
        }

        /** @var User $user */
        $user = $request->user();

        // Check if approval is actually required
        if (! $this->requestApprovalAction->requiresApproval($deployment, $user)) {
            return response()->json([
                'message' => 'This deployment does not require approval.',
            ], 400);
        }

        try {
            $approval = $this->requestApprovalAction->handle($deployment, $user);

            return response()->json([
                'message' => $approval->wasRecentlyCreated
                    ? 'Approval request created successfully.'
                    : 'Approval request already exists.',
                'approval_uuid' => $approval->uuid,
                'status' => $approval->status,
            ]);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        }
    }

    #[OA\Post(
        summary: 'Approve deployment',
        description: 'Approve a pending deployment request.',
        path: '/deployments/{uuid}/approve',
        operationId: 'approve-deployment',
        security: [
            ['bearerAuth' => []],
        ],
        tags: ['Deployment Approvals'],
        parameters: [
            new OA\Parameter(name: 'uuid', in: 'path', required: true, description: 'Deployment UUID', schema: new OA\Schema(type: 'string')),
        ],
        requestBody: new OA\RequestBody(
            content: new OA\MediaType(
                mediaType: 'application/json',
                schema: new OA\Schema(
                    type: 'object',
                    properties: [
                        'comment' => new OA\Property(property: 'comment', type: 'string', description: 'Optional approval comment'),
                    ]
                )
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Deployment approved successfully.',
                content: [
                    new OA\MediaType(
                        mediaType: 'application/json',
                        schema: new OA\Schema(
                            type: 'object',
                            properties: [
                                'message' => ['type' => 'string'],
                                'deployment_uuid' => ['type' => 'string'],
                                'status' => ['type' => 'string'],
                            ]
                        )
                    ),
                ]),
            new OA\Response(response: 400, ref: '#/components/responses/400'),
            new OA\Response(response: 401, ref: '#/components/responses/401'),
            new OA\Response(response: 403, description: 'Not authorized to approve this deployment.'),
            new OA\Response(response: 404, ref: '#/components/responses/404'),
        ]
    )]
    public function approve(Request $request, string $uuid)
    {
        $teamId = getTeamIdFromToken();
        if (is_null($teamId)) {
            return invalidTokenResponse();
        }

        $deployment = ApplicationDeploymentQueue::where('deployment_uuid', $uuid)->first();
        if (! $deployment) {
            return response()->json(['message' => 'Deployment not found.'], 404);
        }

        // Check if deployment belongs to team
        $application = $deployment->application;
        if (! $application || $application->team()?->id !== (int) $teamId) {
            return response()->json(['message' => 'Deployment not found.'], 404);
        }

        $approval = DeploymentApproval::where('application_deployment_queue_id', $deployment->id)
            ->where('status', 'pending')
            ->first();

        if (! $approval) {
            return response()->json(['message' => 'No pending approval found for this deployment.'], 404);
        }

        /** @var User $user */
        $user = $request->user();

        try {
            $this->approveAction->approve(
                $approval,
                $user,
                $request->input('comment')
            );

            return response()->json([
                'message' => 'Deployment approved successfully.',
                'deployment_uuid' => $deployment->deployment_uuid,
                'status' => 'approved',
            ]);
        } catch (\Exception $e) {
            $statusCode = str_contains($e->getMessage(), 'permission') ? 403 : 400;

            return response()->json(['message' => $e->getMessage()], $statusCode);
        }
    }

    #[OA\Post(
        summary: 'Reject deployment',
        description: 'Reject a pending deployment request.',
        path: '/deployments/{uuid}/reject',
        operationId: 'reject-deployment',
        security: [
            ['bearerAuth' => []],
        ],
        tags: ['Deployment Approvals'],
        parameters: [
            new OA\Parameter(name: 'uuid', in: 'path', required: true, description: 'Deployment UUID', schema: new OA\Schema(type: 'string')),
        ],
        requestBody: new OA\RequestBody(
            content: new OA\MediaType(
                mediaType: 'application/json',
                schema: new OA\Schema(
                    type: 'object',
                    properties: [
                        'reason' => new OA\Property(property: 'reason', type: 'string', description: 'Rejection reason'),
                    ]
                )
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Deployment rejected successfully.',
                content: [
                    new OA\MediaType(
                        mediaType: 'application/json',
                        schema: new OA\Schema(
                            type: 'object',
                            properties: [
                                'message' => ['type' => 'string'],
                                'deployment_uuid' => ['type' => 'string'],
                                'status' => ['type' => 'string'],
                            ]
                        )
                    ),
                ]),
            new OA\Response(response: 400, ref: '#/components/responses/400'),
            new OA\Response(response: 401, ref: '#/components/responses/401'),
            new OA\Response(response: 403, description: 'Not authorized to reject this deployment.'),
            new OA\Response(response: 404, ref: '#/components/responses/404'),
        ]
    )]
    public function reject(Request $request, string $uuid)
    {
        $teamId = getTeamIdFromToken();
        if (is_null($teamId)) {
            return invalidTokenResponse();
        }

        $deployment = ApplicationDeploymentQueue::where('deployment_uuid', $uuid)->first();
        if (! $deployment) {
            return response()->json(['message' => 'Deployment not found.'], 404);
        }

        // Check if deployment belongs to team
        $application = $deployment->application;
        if (! $application || $application->team()?->id !== (int) $teamId) {
            return response()->json(['message' => 'Deployment not found.'], 404);
        }

        $approval = DeploymentApproval::where('application_deployment_queue_id', $deployment->id)
            ->where('status', 'pending')
            ->first();

        if (! $approval) {
            return response()->json(['message' => 'No pending approval found for this deployment.'], 404);
        }

        /** @var User $user */
        $user = $request->user();

        try {
            $this->approveAction->reject(
                $approval,
                $user,
                $request->input('reason')
            );

            return response()->json([
                'message' => 'Deployment rejected successfully.',
                'deployment_uuid' => $deployment->deployment_uuid,
                'status' => 'rejected',
            ]);
        } catch (\Exception $e) {
            $statusCode = str_contains($e->getMessage(), 'permission') ? 403 : 400;

            return response()->json(['message' => $e->getMessage()], $statusCode);
        }
    }

    #[OA\Get(
        summary: 'List pending approvals for project',
        description: 'Get all pending deployment approvals for a specific project.',
        path: '/projects/{uuid}/pending-approvals',
        operationId: 'list-project-pending-approvals',
        security: [
            ['bearerAuth' => []],
        ],
        tags: ['Deployment Approvals'],
        parameters: [
            new OA\Parameter(name: 'uuid', in: 'path', required: true, description: 'Project UUID', schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'List of pending approvals.',
                content: [
                    new OA\MediaType(
                        mediaType: 'application/json',
                        schema: new OA\Schema(
                            type: 'array',
                            items: new OA\Items(
                                type: 'object',
                                properties: [
                                    'uuid' => ['type' => 'string'],
                                    'status' => ['type' => 'string'],
                                    'deployment_uuid' => ['type' => 'string'],
                                    'application_name' => ['type' => 'string'],
                                    'environment_name' => ['type' => 'string'],
                                    'requested_by' => ['type' => 'string'],
                                    'requested_at' => ['type' => 'string'],
                                ]
                            )
                        )
                    ),
                ]),
            new OA\Response(response: 401, ref: '#/components/responses/401'),
            new OA\Response(response: 404, ref: '#/components/responses/404'),
        ]
    )]
    public function pendingForProject(Request $request, string $uuid)
    {
        $teamId = getTeamIdFromToken();
        if (is_null($teamId)) {
            return invalidTokenResponse();
        }

        $project = Project::whereTeamId($teamId)->where('uuid', $uuid)->first();
        if (! $project) {
            return response()->json(['message' => 'Project not found.'], 404);
        }

        /** @var User $user */
        $user = $request->user();

        // Check if user can view this project
        if (! $project->hasMember($user) && ! $user->isPlatformAdmin()) {
            return response()->json(['message' => 'Project not found.'], 404);
        }

        $approvals = DeploymentApproval::pendingForProject($project)->get();

        $result = $approvals->map(function (DeploymentApproval $approval) {
            return [
                'uuid' => $approval->uuid,
                'status' => $approval->status,
                'deployment_uuid' => $approval->deployment?->deployment_uuid,
                'application_name' => $approval->deployment?->application?->name,
                'environment_name' => $approval->deployment?->application?->environment?->name,
                'requested_by' => $approval->requestedBy?->email,
                'requested_at' => $approval->created_at?->toIso8601String(),
            ];
        });

        return response()->json($result);
    }

    #[OA\Get(
        summary: 'List my pending approvals',
        description: 'Get all pending deployment approvals that the current user can approve.',
        path: '/approvals/pending',
        operationId: 'list-my-pending-approvals',
        security: [
            ['bearerAuth' => []],
        ],
        tags: ['Deployment Approvals'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'List of pending approvals for the current user.',
                content: [
                    new OA\MediaType(
                        mediaType: 'application/json',
                        schema: new OA\Schema(
                            type: 'array',
                            items: new OA\Items(
                                type: 'object',
                                properties: [
                                    'uuid' => ['type' => 'string'],
                                    'status' => ['type' => 'string'],
                                    'deployment_uuid' => ['type' => 'string'],
                                    'application_name' => ['type' => 'string'],
                                    'environment_name' => ['type' => 'string'],
                                    'project_name' => ['type' => 'string'],
                                    'requested_by' => ['type' => 'string'],
                                    'requested_at' => ['type' => 'string'],
                                ]
                            )
                        )
                    ),
                ]),
            new OA\Response(response: 401, ref: '#/components/responses/401'),
        ]
    )]
    public function myPendingApprovals(Request $request)
    {
        $teamId = getTeamIdFromToken();
        if (is_null($teamId)) {
            return invalidTokenResponse();
        }

        /** @var User $user */
        $user = $request->user();

        $approvals = DeploymentApproval::pendingForApprover($user)->get();

        $result = $approvals->map(function (DeploymentApproval $approval) {
            return [
                'uuid' => $approval->uuid,
                'status' => $approval->status,
                'deployment_uuid' => $approval->deployment?->deployment_uuid,
                'application_name' => $approval->deployment?->application?->name,
                'environment_name' => $approval->deployment?->application?->environment?->name,
                'project_name' => $approval->deployment?->application?->environment?->project?->name,
                'requested_by' => $approval->requestedBy?->email,
                'requested_at' => $approval->created_at?->toIso8601String(),
            ];
        });

        return response()->json($result);
    }
}
