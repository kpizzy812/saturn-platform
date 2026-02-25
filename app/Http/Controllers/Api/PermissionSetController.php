<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\PermissionSet\AssignPermissionSetUserRequest;
use App\Http\Requests\Api\PermissionSet\StorePermissionSetRequest;
use App\Http\Requests\Api\PermissionSet\SyncPermissionsRequest;
use App\Http\Requests\Api\PermissionSet\UpdatePermissionSetRequest;
use App\Models\Permission;
use App\Models\PermissionSet;
use App\Models\User;
use App\Services\Authorization\PermissionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;
use OpenApi\Attributes as OA;

class PermissionSetController extends Controller
{
    public function __construct(
        private PermissionService $permissionService
    ) {}

    #[OA\Get(
        summary: 'List Permission Sets',
        description: 'Get all permission sets for the current team.',
        path: '/permission-sets',
        operationId: 'list-permission-sets',
        security: [['bearerAuth' => []]],
        tags: ['Permission Sets'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'List of permission sets.',
                content: new OA\JsonContent(
                    type: 'array',
                    items: new OA\Items(ref: '#/components/schemas/PermissionSet')
                )
            ),
            new OA\Response(response: 401, ref: '#/components/responses/401'),
        ]
    )]
    public function index(): JsonResponse
    {
        $teamId = getTeamIdFromToken();
        if (is_null($teamId)) {
            return invalidTokenResponse();
        }

        $permissionSets = PermissionSet::forTeam($teamId)
            ->with(['permissions', 'users'])
            ->orderBy('is_system', 'desc')
            ->orderBy('name')
            ->get();

        return response()->json([
            'data' => $permissionSets->map(fn ($set) => $this->formatPermissionSet($set)),
        ]);
    }

    #[OA\Get(
        summary: 'List All Permissions',
        description: 'Get all available permissions in the system.',
        path: '/permission-sets/permissions',
        operationId: 'list-all-permissions',
        security: [['bearerAuth' => []]],
        tags: ['Permission Sets'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'List of all permissions grouped by category.',
                content: new OA\JsonContent(
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'data', type: 'object'),
                    ]
                )
            ),
            new OA\Response(response: 401, ref: '#/components/responses/401'),
        ]
    )]
    public function permissions(): JsonResponse
    {
        $teamId = getTeamIdFromToken();
        if (is_null($teamId)) {
            return invalidTokenResponse();
        }

        $permissions = Permission::orderBy('sort_order')
            ->get()
            ->groupBy('category')
            ->map(function (\Illuminate\Database\Eloquent\Collection $group, int|string $key): array {
                return $group->map(fn (\App\Models\Permission $p) => [
                    'id' => $p->id,
                    'key' => $p->key,
                    'name' => $p->name,
                    'description' => $p->description,
                    'resource' => $p->resource,
                    'action' => $p->action,
                    'is_sensitive' => $p->is_sensitive,
                ])->all();
            });

        return response()->json([
            'data' => $permissions,
        ]);
    }

    #[OA\Get(
        summary: 'Get Permission Set',
        description: 'Get a specific permission set by ID.',
        path: '/permission-sets/{id}',
        operationId: 'get-permission-set',
        security: [['bearerAuth' => []]],
        tags: ['Permission Sets'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Permission set details.',
                content: new OA\JsonContent(ref: '#/components/schemas/PermissionSet')
            ),
            new OA\Response(response: 401, ref: '#/components/responses/401'),
            new OA\Response(response: 404, ref: '#/components/responses/404'),
        ]
    )]
    public function show(int $id): JsonResponse
    {
        $teamId = getTeamIdFromToken();
        if (is_null($teamId)) {
            return invalidTokenResponse();
        }

        $permissionSet = PermissionSet::forTeam($teamId)
            ->with(['permissions', 'users', 'parent'])
            ->find($id);

        if (! $permissionSet) {
            return response()->json(['message' => 'Permission set not found.'], 404);
        }

        return response()->json([
            'data' => $this->formatPermissionSet($permissionSet, true),
        ]);
    }

    #[OA\Post(
        summary: 'Create Permission Set',
        description: 'Create a new custom permission set.',
        path: '/permission-sets',
        operationId: 'create-permission-set',
        security: [['bearerAuth' => []]],
        tags: ['Permission Sets'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['name'],
                properties: [
                    new OA\Property(property: 'name', type: 'string', example: 'QA Engineer'),
                    new OA\Property(property: 'description', type: 'string', example: 'Quality assurance team member'),
                    new OA\Property(property: 'color', type: 'string', example: 'purple'),
                    new OA\Property(property: 'icon', type: 'string', example: 'flask'),
                    new OA\Property(property: 'parent_id', type: 'integer', description: 'ID of parent permission set to inherit from'),
                    new OA\Property(
                        property: 'permissions',
                        type: 'array',
                        items: new OA\Items(
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'permission_id', type: 'integer'),
                                new OA\Property(property: 'environment_restrictions', type: 'object'),
                            ]
                        )
                    ),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Permission set created.'),
            new OA\Response(response: 401, ref: '#/components/responses/401'),
            new OA\Response(response: 403, ref: '#/components/responses/403'),
            new OA\Response(response: 422, description: 'Validation error.'),
        ]
    )]
    public function store(StorePermissionSetRequest $request): JsonResponse
    {
        $teamId = getTeamIdFromToken();
        if (is_null($teamId)) {
            return invalidTokenResponse();
        }

        // Check permission
        if (! $this->permissionService->userHasPermission(auth()->user(), 'team.manage_roles')) {
            return response()->json(['message' => 'Unauthorized. You do not have permission to manage permission sets.'], 403);
        }

        $validated = $request->validated();

        $slug = Str::slug($validated['name']);

        // Check if slug already exists in this team
        if (PermissionSet::forTeam($teamId)->where('slug', $slug)->exists()) {
            return response()->json(['message' => 'A permission set with this name already exists.'], 422);
        }

        $permissionSet = PermissionSet::create([
            'name' => $validated['name'],
            'slug' => $slug,
            'description' => $validated['description'] ?? null,
            'scope_type' => 'team',
            'scope_id' => $teamId,
            'is_system' => false,
            'parent_id' => $validated['parent_id'] ?? null,
            'color' => $validated['color'] ?? null,
            'icon' => $validated['icon'] ?? null,
        ]);

        // Sync permissions if provided
        if (! empty($validated['permissions'])) {
            $permissionSet->syncPermissionsWithRestrictions($validated['permissions']);
        }

        $permissionSet->load(['permissions', 'parent']);

        return response()->json([
            'message' => 'Permission set created successfully.',
            'data' => $this->formatPermissionSet($permissionSet, true),
        ], 201);
    }

    #[OA\Put(
        summary: 'Update Permission Set',
        description: 'Update an existing permission set.',
        path: '/permission-sets/{id}',
        operationId: 'update-permission-set',
        security: [['bearerAuth' => []]],
        tags: ['Permission Sets'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'name', type: 'string'),
                    new OA\Property(property: 'description', type: 'string'),
                    new OA\Property(property: 'color', type: 'string'),
                    new OA\Property(property: 'icon', type: 'string'),
                    new OA\Property(property: 'parent_id', type: 'integer'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Permission set updated.'),
            new OA\Response(response: 401, ref: '#/components/responses/401'),
            new OA\Response(response: 403, ref: '#/components/responses/403'),
            new OA\Response(response: 404, ref: '#/components/responses/404'),
        ]
    )]
    public function update(UpdatePermissionSetRequest $request, int $id): JsonResponse
    {
        $teamId = getTeamIdFromToken();
        if (is_null($teamId)) {
            return invalidTokenResponse();
        }

        // Check permission
        if (! $this->permissionService->userHasPermission(auth()->user(), 'team.manage_roles')) {
            return response()->json(['message' => 'Unauthorized. You do not have permission to manage permission sets.'], 403);
        }

        $permissionSet = PermissionSet::forTeam($teamId)->find($id);

        if (! $permissionSet) {
            return response()->json(['message' => 'Permission set not found.'], 404);
        }

        $validated = $request->validated();

        // Update only non-system fields
        $updateData = array_filter([
            'description' => $validated['description'] ?? $permissionSet->description,
            'color' => $validated['color'] ?? $permissionSet->color,
            'icon' => $validated['icon'] ?? $permissionSet->icon,
        ]);

        if (! $permissionSet->is_system) {
            if (isset($validated['name'])) {
                $newSlug = Str::slug($validated['name']);
                if ($newSlug !== $permissionSet->slug && PermissionSet::forTeam($teamId)->where('slug', $newSlug)->exists()) {
                    return response()->json(['message' => 'A permission set with this name already exists.'], 422);
                }
                $updateData['name'] = $validated['name'];
                $updateData['slug'] = $newSlug;
            }
            if (array_key_exists('parent_id', $validated)) {
                $updateData['parent_id'] = $validated['parent_id'];
            }
        }

        $permissionSet->update($updateData);
        $permissionSet->load(['permissions', 'parent']);

        // Clear permission cache for all users with this set
        $this->permissionService->clearTeamCache(auth()->user()->currentTeam());

        return response()->json([
            'message' => 'Permission set updated successfully.',
            'data' => $this->formatPermissionSet($permissionSet, true),
        ]);
    }

    #[OA\Delete(
        summary: 'Delete Permission Set',
        description: 'Delete a custom permission set.',
        path: '/permission-sets/{id}',
        operationId: 'delete-permission-set',
        security: [['bearerAuth' => []]],
        tags: ['Permission Sets'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Permission set deleted.'),
            new OA\Response(response: 401, ref: '#/components/responses/401'),
            new OA\Response(response: 403, ref: '#/components/responses/403'),
            new OA\Response(response: 404, ref: '#/components/responses/404'),
        ]
    )]
    public function destroy(int $id): JsonResponse
    {
        $teamId = getTeamIdFromToken();
        if (is_null($teamId)) {
            return invalidTokenResponse();
        }

        // Check permission
        if (! $this->permissionService->userHasPermission(auth()->user(), 'team.manage_roles')) {
            return response()->json(['message' => 'Unauthorized. You do not have permission to manage permission sets.'], 403);
        }

        $permissionSet = PermissionSet::forTeam($teamId)->find($id);

        if (! $permissionSet) {
            return response()->json(['message' => 'Permission set not found.'], 404);
        }

        if (! $permissionSet->canBeDeleted()) {
            if ($permissionSet->is_system) {
                return response()->json(['message' => 'System permission sets cannot be deleted.'], 403);
            }
            if ($permissionSet->users()->exists()) {
                return response()->json(['message' => 'Cannot delete permission set that is assigned to users. Please reassign users first.'], 403);
            }
            if ($permissionSet->children()->exists()) {
                return response()->json(['message' => 'Cannot delete permission set that has child sets. Please delete or reassign child sets first.'], 403);
            }
        }

        $permissionSet->delete();

        return response()->json([
            'message' => 'Permission set deleted successfully.',
        ]);
    }

    #[OA\Post(
        summary: 'Sync Permissions',
        description: 'Sync permissions for a permission set.',
        path: '/permission-sets/{id}/permissions',
        operationId: 'sync-permission-set-permissions',
        security: [['bearerAuth' => []]],
        tags: ['Permission Sets'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['permissions'],
                properties: [
                    new OA\Property(
                        property: 'permissions',
                        type: 'array',
                        items: new OA\Items(
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'permission_id', type: 'integer'),
                                new OA\Property(
                                    property: 'environment_restrictions',
                                    type: 'object',
                                    example: ['production' => false]
                                ),
                            ]
                        )
                    ),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Permissions synced.'),
            new OA\Response(response: 401, ref: '#/components/responses/401'),
            new OA\Response(response: 403, ref: '#/components/responses/403'),
            new OA\Response(response: 404, ref: '#/components/responses/404'),
        ]
    )]
    public function syncPermissions(SyncPermissionsRequest $request, int $id): JsonResponse
    {
        $teamId = getTeamIdFromToken();
        if (is_null($teamId)) {
            return invalidTokenResponse();
        }

        // Check permission
        if (! $this->permissionService->userHasPermission(auth()->user(), 'team.manage_roles')) {
            return response()->json(['message' => 'Unauthorized. You do not have permission to manage permission sets.'], 403);
        }

        $permissionSet = PermissionSet::forTeam($teamId)->find($id);

        if (! $permissionSet) {
            return response()->json(['message' => 'Permission set not found.'], 404);
        }

        // System permission sets permissions can still be modified (for admin customization)

        $validated = $request->validated();

        $permissionSet->syncPermissionsWithRestrictions($validated['permissions']);

        // Clear permission cache
        $this->permissionService->clearTeamCache(auth()->user()->currentTeam());

        $permissionSet->load('permissions');

        return response()->json([
            'message' => 'Permissions synced successfully.',
            'data' => $this->formatPermissionSet($permissionSet, true),
        ]);
    }

    #[OA\Post(
        summary: 'Assign User',
        description: 'Assign a permission set to a user.',
        path: '/permission-sets/{id}/users',
        operationId: 'assign-permission-set-user',
        security: [['bearerAuth' => []]],
        tags: ['Permission Sets'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['user_id'],
                properties: [
                    new OA\Property(property: 'user_id', type: 'integer'),
                    new OA\Property(
                        property: 'environment_overrides',
                        type: 'object',
                        example: ['production' => false],
                        description: 'Per-user environment overrides'
                    ),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'User assigned to permission set.'),
            new OA\Response(response: 401, ref: '#/components/responses/401'),
            new OA\Response(response: 403, ref: '#/components/responses/403'),
            new OA\Response(response: 404, ref: '#/components/responses/404'),
        ]
    )]
    public function assignUser(AssignPermissionSetUserRequest $request, int $id): JsonResponse
    {
        $teamId = getTeamIdFromToken();
        if (is_null($teamId)) {
            return invalidTokenResponse();
        }

        // Check permission
        if (! $this->permissionService->userHasPermission(auth()->user(), 'team.manage_members')) {
            return response()->json(['message' => 'Unauthorized. You do not have permission to manage team members.'], 403);
        }

        $permissionSet = PermissionSet::forTeam($teamId)->find($id);

        if (! $permissionSet) {
            return response()->json(['message' => 'Permission set not found.'], 404);
        }

        $validated = $request->validated();

        $user = User::find($validated['user_id']);

        // Verify user is a team member
        $team = auth()->user()->currentTeam();
        if (! $team->members->contains($user)) {
            return response()->json(['message' => 'User is not a member of this team.'], 404);
        }

        // Assign permission set
        $this->permissionService->assignPermissionSet(
            $user,
            $permissionSet,
            'team',
            $teamId,
            $validated['environment_overrides'] ?? null
        );

        return response()->json([
            'message' => 'User assigned to permission set successfully.',
        ]);
    }

    #[OA\Delete(
        summary: 'Remove User Assignment',
        description: 'Remove a user from a permission set.',
        path: '/permission-sets/{id}/users/{userId}',
        operationId: 'remove-permission-set-user',
        security: [['bearerAuth' => []]],
        tags: ['Permission Sets'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'userId', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'User removed from permission set.'),
            new OA\Response(response: 401, ref: '#/components/responses/401'),
            new OA\Response(response: 403, ref: '#/components/responses/403'),
            new OA\Response(response: 404, ref: '#/components/responses/404'),
        ]
    )]
    public function removeUser(int $id, int $userId): JsonResponse
    {
        $teamId = getTeamIdFromToken();
        if (is_null($teamId)) {
            return invalidTokenResponse();
        }

        // Check permission
        if (! $this->permissionService->userHasPermission(auth()->user(), 'team.manage_members')) {
            return response()->json(['message' => 'Unauthorized. You do not have permission to manage team members.'], 403);
        }

        $permissionSet = PermissionSet::forTeam($teamId)->find($id);

        if (! $permissionSet) {
            return response()->json(['message' => 'Permission set not found.'], 404);
        }

        $user = User::find($userId);

        if (! $user) {
            return response()->json(['message' => 'User not found.'], 404);
        }

        // Verify user is a member of the current team
        $team = auth()->user()->currentTeam();
        if (! $team->members->contains($user)) {
            return response()->json(['message' => 'User is not a member of this team.'], 404);
        }

        // Remove assignment
        $this->permissionService->removePermissionSetAssignment($user, 'team', $teamId);

        return response()->json([
            'message' => 'User removed from permission set successfully.',
        ]);
    }

    #[OA\Get(
        summary: 'Get My Permissions',
        description: 'Get current user effective permissions.',
        path: '/permission-sets/my-permissions',
        operationId: 'get-my-permissions',
        security: [['bearerAuth' => []]],
        tags: ['Permission Sets'],
        responses: [
            new OA\Response(response: 200, description: 'User permissions.'),
            new OA\Response(response: 401, ref: '#/components/responses/401'),
        ]
    )]
    public function myPermissions(): JsonResponse
    {
        $teamId = getTeamIdFromToken();
        if (is_null($teamId)) {
            return invalidTokenResponse();
        }

        $user = auth()->user();
        $permissions = $this->permissionService->getUserEffectivePermissionsGrouped($user);
        $permissionSet = $this->permissionService->getUserPermissionSet($user);

        return response()->json([
            'data' => [
                'permission_set' => $permissionSet ? [
                    'id' => $permissionSet->id,
                    'name' => $permissionSet->name,
                    'slug' => $permissionSet->slug,
                    'is_system' => $permissionSet->is_system,
                ] : null,
                'permissions' => $permissions,
            ],
        ]);
    }

    /**
     * Format a permission set for API response.
     */
    private function formatPermissionSet(PermissionSet $set, bool $detailed = false): array
    {
        $data = [
            'id' => $set->id,
            'name' => $set->name,
            'slug' => $set->slug,
            'description' => $set->description,
            'is_system' => $set->is_system,
            'color' => $set->color,
            'icon' => $set->icon,
            'users_count' => $set->users->count(),
            'created_at' => $set->created_at->toIso8601String(),
            'updated_at' => $set->updated_at->toIso8601String(),
        ];

        if ($detailed) {
            $data['parent'] = $set->parent ? [
                'id' => $set->parent->id,
                'name' => $set->parent->name,
                'slug' => $set->parent->slug,
            ] : null;

            $data['permissions'] = $set->permissions->map(fn ($p) => [
                'id' => $p->id,
                'key' => $p->key,
                'name' => $p->name,
                'category' => $p->category,
                'environment_restrictions' => $p->pivot?->getAttribute('environment_restrictions'),
            ]);

            $data['users'] = $set->users->map(function ($u) {
                /** @var \Illuminate\Database\Eloquent\Relations\Pivot|null $userPivot */
                $userPivot = data_get($u, 'pivot');

                return [
                    'id' => $u->id,
                    'name' => $u->name,
                    'email' => $u->email,
                    'environment_overrides' => $userPivot?->getAttribute('environment_overrides'),
                ];
            });
        }

        return $data;
    }
}
