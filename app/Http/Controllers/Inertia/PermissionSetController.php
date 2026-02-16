<?php

namespace App\Http\Controllers\Inertia;

use App\Http\Controllers\Controller;
use App\Models\Environment;
use App\Models\Permission;
use App\Models\PermissionSet;
use App\Services\Authorization\PermissionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class PermissionSetController extends Controller
{
    public function __construct(
        private PermissionService $permissionService
    ) {}

    /**
     * Display permission sets list.
     */
    public function index(): Response|RedirectResponse
    {
        $user = auth()->user();
        $team = $user->currentTeam();

        // Check if user can view permission sets (admin+ can view, manage_roles permission to edit)
        $canManageRoles = $this->permissionService->userHasPermission($user, 'team.manage_roles');
        $canViewPermissionSets = $this->permissionService->userHasPermission($user, 'team.manage_members')
            || $canManageRoles;

        if (! $canViewPermissionSets) {
            return redirect()->route('settings.team')
                ->with('error', 'You do not have permission to view permission sets.');
        }

        $permissionSets = PermissionSet::forTeam($team->id)
            ->with(['permissions', 'users'])
            ->orderBy('is_system', 'desc')
            ->orderBy('name')
            ->get()
            ->map(fn ($set) => $this->formatPermissionSet($set));

        return Inertia::render('Settings/Team/PermissionSets/Index', [
            'permissionSets' => $permissionSets,
            'canManageRoles' => $canManageRoles,
        ]);
    }

    /**
     * Display a specific permission set.
     */
    public function show(int $id): Response
    {
        $team = auth()->user()->currentTeam();

        $permissionSet = PermissionSet::forTeam($team->id)
            ->with(['permissions', 'users', 'parent'])
            ->findOrFail($id);

        $allPermissions = Permission::orderBy('sort_order')
            ->get()
            ->groupBy('category')
            ->map(fn ($group) => $group->map(fn ($p) => [
                'id' => $p->id,
                'key' => $p->key,
                'name' => $p->name,
                'description' => $p->description,
                'resource' => $p->resource,
                'action' => $p->action,
                'is_sensitive' => $p->is_sensitive,
            ]));

        return Inertia::render('Settings/Team/PermissionSets/Show', [
            'permissionSet' => $this->formatPermissionSet($permissionSet, true),
            'allPermissions' => $allPermissions,
        ]);
    }

    /**
     * Display create permission set form.
     */
    public function create(): Response|RedirectResponse
    {
        $user = auth()->user();
        $team = $user->currentTeam();

        // Check permission
        if (! $this->permissionService->userHasPermission($user, 'team.manage_roles')) {
            return redirect()->route('settings.team.permission-sets.index')
                ->with('error', 'You do not have permission to create permission sets.');
        }

        $allPermissions = Permission::orderBy('sort_order')
            ->get()
            ->groupBy('category')
            ->map(fn ($group) => $group->map(fn ($p) => [
                'id' => $p->id,
                'key' => $p->key,
                'name' => $p->name,
                'description' => $p->description,
                'resource' => $p->resource,
                'action' => $p->action,
                'is_sensitive' => $p->is_sensitive,
            ]));

        // Get existing permission sets that can be parents
        $parentSets = PermissionSet::forTeam($team->id)
            ->select('id', 'name', 'slug')
            ->orderBy('name')
            ->get();

        // Get environments for restriction options
        $environments = Environment::whereHas('project', function ($query) use ($team) {
            $query->where('team_id', $team->id);
        })
            ->select('id', 'name')
            ->distinct('name')
            ->get();

        return Inertia::render('Settings/Team/PermissionSets/Create', [
            'allPermissions' => $allPermissions,
            'parentSets' => $parentSets,
            'environments' => $environments,
        ]);
    }

    /**
     * Store a new permission set.
     */
    public function store(Request $request): RedirectResponse
    {
        $team = auth()->user()->currentTeam();

        // Check permission
        if (! $this->permissionService->userHasPermission(auth()->user(), 'team.manage_roles')) {
            return redirect()->back()->withErrors(['permission' => 'You do not have permission to manage permission sets.']);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:500'],
            'color' => ['nullable', 'string', 'max:50'],
            'icon' => ['nullable', 'string', 'max:50'],
            'parent_id' => [
                'nullable',
                'integer',
                Rule::exists('permission_sets', 'id')
                    ->where('scope_type', 'team')
                    ->where('scope_id', $team->id),
            ],
            'permissions' => ['array'],
            'permissions.*.permission_id' => ['required_with:permissions', 'integer', 'exists:permissions,id'],
            'permissions.*.environment_restrictions' => ['nullable', 'array'],
        ]);

        $slug = Str::slug($validated['name']);

        // Check if slug already exists in this team
        if (PermissionSet::forTeam($team->id)->where('slug', $slug)->exists()) {
            return redirect()->back()->withErrors(['name' => 'A permission set with this name already exists.']);
        }

        $permissionSet = PermissionSet::create([
            'name' => $validated['name'],
            'slug' => $slug,
            'description' => $validated['description'] ?? null,
            'scope_type' => 'team',
            'scope_id' => $team->id,
            'is_system' => false,
            'parent_id' => $validated['parent_id'] ?? null,
            'color' => $validated['color'] ?? 'primary',
            'icon' => $validated['icon'] ?? 'shield',
        ]);

        // Sync permissions if provided
        if (! empty($validated['permissions'])) {
            $permissionSet->syncPermissionsWithRestrictions($validated['permissions']);
        }

        return redirect()->route('settings.team.permission-sets.index')
            ->with('success', 'Permission set created successfully.');
    }

    /**
     * Display edit permission set form.
     */
    public function edit(int $id): Response|RedirectResponse
    {
        $user = auth()->user();
        $team = $user->currentTeam();

        // Check permission
        if (! $this->permissionService->userHasPermission($user, 'team.manage_roles')) {
            return redirect()->route('settings.team.permission-sets.show', $id)
                ->with('error', 'You do not have permission to edit permission sets.');
        }

        $permissionSet = PermissionSet::forTeam($team->id)
            ->with(['permissions', 'parent'])
            ->findOrFail($id);

        if ($permissionSet->is_system) {
            return redirect()->route('settings.team.permission-sets.show', $id);
        }

        $allPermissions = Permission::orderBy('sort_order')
            ->get()
            ->groupBy('category')
            ->map(fn ($group) => $group->map(fn ($p) => [
                'id' => $p->id,
                'key' => $p->key,
                'name' => $p->name,
                'description' => $p->description,
                'resource' => $p->resource,
                'action' => $p->action,
                'is_sensitive' => $p->is_sensitive,
            ]));

        // Get existing permission sets that can be parents (excluding self and children)
        $parentSets = PermissionSet::forTeam($team->id)
            ->where('id', '!=', $id)
            ->whereNotIn('parent_id', [$id])
            ->select('id', 'name', 'slug')
            ->orderBy('name')
            ->get();

        // Get environments for restriction options
        $environments = Environment::whereHas('project', function ($query) use ($team) {
            $query->where('team_id', $team->id);
        })
            ->select('id', 'name')
            ->distinct('name')
            ->get();

        return Inertia::render('Settings/Team/PermissionSets/Edit', [
            'permissionSet' => $this->formatPermissionSet($permissionSet, true),
            'allPermissions' => $allPermissions,
            'parentSets' => $parentSets,
            'environments' => $environments,
        ]);
    }

    /**
     * Update a permission set.
     */
    public function update(Request $request, int $id): RedirectResponse
    {
        $team = auth()->user()->currentTeam();

        // Check permission
        if (! $this->permissionService->userHasPermission(auth()->user(), 'team.manage_roles')) {
            return redirect()->back()->withErrors(['permission' => 'You do not have permission to manage permission sets.']);
        }

        $permissionSet = PermissionSet::forTeam($team->id)->findOrFail($id);

        // System permission sets can only have description, color, icon and permissions updated
        $rules = [
            'description' => ['nullable', 'string', 'max:500'],
            'color' => ['nullable', 'string', 'max:50'],
            'icon' => ['nullable', 'string', 'max:50'],
            'permissions' => ['array'],
            'permissions.*.permission_id' => ['required_with:permissions', 'integer', 'exists:permissions,id'],
            'permissions.*.environment_restrictions' => ['nullable', 'array'],
        ];

        if (! $permissionSet->is_system) {
            $rules['name'] = ['string', 'max:100'];
            $rules['parent_id'] = [
                'nullable',
                'integer',
                Rule::exists('permission_sets', 'id')
                    ->where('scope_type', 'team')
                    ->where('scope_id', $team->id),
            ];
        }

        $validated = $request->validate($rules);

        // Update basic fields
        $updateData = [
            'description' => $validated['description'] ?? $permissionSet->description,
            'color' => $validated['color'] ?? $permissionSet->color,
            'icon' => $validated['icon'] ?? $permissionSet->icon,
        ];

        if (! $permissionSet->is_system) {
            if (isset($validated['name'])) {
                $newSlug = Str::slug($validated['name']);
                if ($newSlug !== $permissionSet->slug &&
                    PermissionSet::forTeam($team->id)->where('slug', $newSlug)->exists()) {
                    return redirect()->back()->withErrors(['name' => 'A permission set with this name already exists.']);
                }
                $updateData['name'] = $validated['name'];
                $updateData['slug'] = $newSlug;
            }
            if (array_key_exists('parent_id', $validated)) {
                $updateData['parent_id'] = $validated['parent_id'];
            }
        }

        $permissionSet->update($updateData);

        // Sync permissions if provided
        if (isset($validated['permissions'])) {
            $permissionSet->syncPermissionsWithRestrictions($validated['permissions']);
        }

        // Clear permission cache
        $this->permissionService->clearTeamCache($team);

        return redirect()->route('settings.team.permission-sets.show', $id)
            ->with('success', 'Permission set updated successfully.');
    }

    /**
     * Delete a permission set.
     */
    public function destroy(int $id): RedirectResponse
    {
        $team = auth()->user()->currentTeam();

        // Check permission
        if (! $this->permissionService->userHasPermission(auth()->user(), 'team.manage_roles')) {
            return redirect()->back()->withErrors(['permission' => 'You do not have permission to manage permission sets.']);
        }

        $permissionSet = PermissionSet::forTeam($team->id)->findOrFail($id);

        if (! $permissionSet->canBeDeleted()) {
            if ($permissionSet->is_system) {
                return redirect()->back()->withErrors(['permission_set' => 'System permission sets cannot be deleted.']);
            }
            if ($permissionSet->users()->exists()) {
                return redirect()->back()->withErrors(['permission_set' => 'Cannot delete permission set that is assigned to users. Please reassign users first.']);
            }
            if ($permissionSet->children()->exists()) {
                return redirect()->back()->withErrors(['permission_set' => 'Cannot delete permission set that has child sets. Please delete or reassign child sets first.']);
            }
        }

        $permissionSet->delete();

        return redirect()->route('settings.team.permission-sets.index')
            ->with('success', 'Permission set deleted successfully.');
    }

    /**
     * Format permission set for frontend.
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
                'description' => $p->description,
                'category' => $p->category,
                'is_sensitive' => $p->is_sensitive,
                'environment_restrictions' => $p->pivot->environment_restrictions,
            ]);

            $data['users'] = $set->users->map(fn ($u) => [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
                'environment_overrides' => $u->pivot->environment_overrides,
            ]);
        }

        return $data;
    }
}
