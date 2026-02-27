<?php

/**
 * Admin Users routes
 *
 * User management including listing, impersonation, suspension, bulk operations, and export.
 */

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/users', function () {
    // Get search and filter parameters
    $search = request()->input('search', '');
    $statusFilter = request()->input('status', 'all');
    $sortBy = request()->input('sort_by', 'created_at');
    $sortDirection = request()->input('sort_direction', 'desc');

    // Build query with search and filters
    $query = \App\Models\User::with(['teams'])
        ->withCount(['teams']);

    // Apply search filter
    if ($search) {
        $query->where(function ($q) use ($search) {
            $q->where('name', 'ILIKE', "%{$search}%")
                ->orWhere('email', 'ILIKE', "%{$search}%");
        });
    }

    // Apply status filter
    if ($statusFilter !== 'all') {
        if ($statusFilter === 'pending') {
            // Pending = active status but email not verified
            $query->where('status', 'active')
                ->whereNull('email_verified_at');
        } else {
            $query->where('status', $statusFilter);
        }
    }

    // Apply sorting
    $validSortFields = ['name', 'email', 'created_at', 'last_login_at', 'teams_count'];
    if (in_array($sortBy, $validSortFields)) {
        $query->orderBy($sortBy, $sortDirection);
    } else {
        $query->latest();
    }

    // Paginate results
    $paginator = $query->paginate(50)->withQueryString();

    $users = $paginator->through(function ($user) {
        // Count servers across all user's teams
        $serversCount = $user->teams->sum(function ($team) {
            return $team->servers()->count();
        });

        // Determine real status from database
        $status = $user->status ?? 'active';

        // If email not verified and status is default, set to pending
        if ($status === 'active' && is_null($user->email_verified_at)) {
            $status = 'pending';
        }

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'status' => $status,
            'is_root_user' => $user->id === 0 || $user->is_superadmin,
            'teams_count' => $user->teams_count,
            'servers_count' => $serversCount,
            'created_at' => $user->created_at->toISOString(),
            'last_login_at' => $user->last_login_at?->toISOString() ?? $user->updated_at?->toISOString(),
            'suspended_at' => $user->suspended_at?->toISOString(),
            'suspension_reason' => $user->suspension_reason,
        ];
    });

    return Inertia::render('Admin/Users/Index', [
        'users' => $users->items(),
        'total' => $paginator->total(),
        'currentPage' => $paginator->currentPage(),
        'perPage' => $paginator->perPage(),
        'lastPage' => $paginator->lastPage(),
        'filters' => [
            'search' => $search,
            'status' => $statusFilter,
            'sort_by' => $sortBy,
            'sort_direction' => $sortDirection,
        ],
    ]);
})->name('admin.users.index');

Route::get('/users/{id}', function (int $id) {
    // Fetch specific user with all relationships
    $user = \App\Models\User::with(['teams'])
        ->withCount(['teams'])
        ->findOrFail($id);

    // Map teams with proper role from pivot table
    $teams = $user->teams->map(function ($team) use ($user) {
        return [
            'id' => $team->id,
            'name' => $team->name,
            'personal_team' => $team->personal_team,
            'user_id' => $team->user_id, // Owner of the team
            'is_owner' => $team->user_id === $user->id, // Is this user the team owner?
            'role' => $team->pivot->role ?? 'member',
            'created_at' => $team->created_at,
        ];
    });

    return Inertia::render('Admin/Users/Show', [
        'user' => [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'email_verified_at' => $user->email_verified_at,
            'is_superadmin' => $user->isSuperAdmin(),
            'platform_role' => $user->getPlatformRole(),
            'force_password_reset' => $user->force_password_reset ?? false,
            'created_at' => $user->created_at,
            'updated_at' => $user->updated_at,
            'teams' => $teams,
        ],
    ]);
})->name('admin.users.show')->whereNumber('id');

Route::post('/users/{id}/impersonate', function (int $id) {
    $adminUser = Auth::user();

    // Only superadmins can impersonate
    if (! $adminUser->isSuperAdmin()) {
        return back()->with('error', 'Unauthorized: Only superadmins can impersonate users');
    }

    $targetUser = \App\Models\User::findOrFail($id);

    // Cannot impersonate root user (id=0) or other superadmins
    if ($targetUser->id === 0 || $targetUser->isSuperAdmin()) {
        return back()->with('error', 'Cannot impersonate root user or other superadmins');
    }

    // Cannot impersonate suspended/banned users
    if ($targetUser->isSuspended() || $targetUser->isBanned()) {
        return back()->with('error', 'Cannot impersonate suspended or banned users');
    }

    // Store original user ID in session for returning later
    session(['impersonating_user_id' => $adminUser->id]);

    // Log the impersonation event
    \App\Models\AuditLog::create([
        'user_id' => $adminUser->id,
        'team_id' => $targetUser->currentTeam()?->id,
        'action' => 'user_impersonated',
        'resource_type' => 'User',
        'resource_id' => $targetUser->id,
        'resource_name' => $targetUser->name,
        'description' => "Admin {$adminUser->name} impersonated user {$targetUser->name}",
        'ip_address' => request()->ip(),
        'user_agent' => request()->userAgent(),
    ]);

    // Login as target user
    Auth::login($targetUser);

    return redirect()->route('dashboard')->with('success', "Now impersonating {$targetUser->name}. You will be automatically logged back as admin after 30 minutes or when you logout.");
})->name('admin.users.impersonate');

Route::post('/users/{id}/toggle-suspension', function (int $id) {
    $adminUser = Auth::user();

    // Only superadmins can suspend users
    if (! $adminUser->isSuperAdmin()) {
        return back()->with('error', 'Unauthorized: Only superadmins can suspend users');
    }

    $targetUser = \App\Models\User::findOrFail($id);

    // Cannot suspend root user (id=0) or other superadmins
    if ($targetUser->id === 0 || $targetUser->isSuperAdmin()) {
        return back()->with('error', 'Cannot suspend root user or other superadmins');
    }

    // Toggle suspension status
    if ($targetUser->isSuspended()) {
        // Activate user
        $targetUser->activate();

        // Log the activation
        \App\Models\AuditLog::create([
            'user_id' => $adminUser->id,
            'team_id' => null,
            'action' => 'user_activated',
            'resource_type' => 'User',
            'resource_id' => $targetUser->id,
            'resource_name' => $targetUser->name,
            'description' => "Admin {$adminUser->name} activated user {$targetUser->name}",
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);

        return back()->with('success', "User {$targetUser->name} has been activated");
    } else {
        // Suspend user
        $reason = request()->input('reason', 'No reason provided');
        $targetUser->suspend($reason, $adminUser->id);

        // Log the suspension
        \App\Models\AuditLog::create([
            'user_id' => $adminUser->id,
            'team_id' => null,
            'action' => 'user_suspended',
            'resource_type' => 'User',
            'resource_id' => $targetUser->id,
            'resource_name' => $targetUser->name,
            'description' => "Admin {$adminUser->name} suspended user {$targetUser->name}. Reason: {$reason}",
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);

        return back()->with('success', "User {$targetUser->name} has been suspended");
    }
})->name('admin.users.toggle-suspension');

// Bulk user operations
Route::post('/users/bulk-suspend', function () {
    $adminUser = Auth::user();

    if (! $adminUser->isSuperAdmin()) {
        return back()->with('error', 'Unauthorized: Only superadmins can suspend users');
    }

    $userIds = request()->input('user_ids', []);
    if (empty($userIds)) {
        return back()->with('error', 'No users selected');
    }

    $reason = request()->input('reason', 'Bulk suspension by admin');
    $suspendedCount = 0;
    $skippedCount = 0;

    foreach ($userIds as $userId) {
        $user = \App\Models\User::find($userId);
        if (! $user || $user->id === 0 || $user->isSuperAdmin()) {
            $skippedCount++;

            continue;
        }

        if (! $user->isSuspended()) {
            $user->suspend($reason, $adminUser->id);
            $suspendedCount++;
        }
    }

    // Log bulk action
    \App\Models\AuditLog::create([
        'user_id' => $adminUser->id,
        'team_id' => null,
        'action' => 'users_bulk_suspended',
        'resource_type' => 'User',
        'resource_id' => null,
        'resource_name' => "{$suspendedCount} users",
        'description' => "Admin {$adminUser->name} bulk suspended {$suspendedCount} users",
        'ip_address' => request()->ip(),
        'user_agent' => request()->userAgent(),
    ]);

    return back()->with('success', "Suspended {$suspendedCount} users".($skippedCount > 0 ? " ({$skippedCount} skipped)" : ''));
})->name('admin.users.bulk-suspend');

Route::post('/users/bulk-activate', function () {
    $adminUser = Auth::user();

    if (! $adminUser->isSuperAdmin()) {
        return back()->with('error', 'Unauthorized: Only superadmins can activate users');
    }

    $userIds = request()->input('user_ids', []);
    if (empty($userIds)) {
        return back()->with('error', 'No users selected');
    }

    $activatedCount = 0;

    foreach ($userIds as $userId) {
        $user = \App\Models\User::find($userId);
        if (! $user) {
            continue;
        }

        if ($user->isSuspended()) {
            $user->activate();
            $activatedCount++;
        }
    }

    // Log bulk action
    \App\Models\AuditLog::create([
        'user_id' => $adminUser->id,
        'team_id' => null,
        'action' => 'users_bulk_activated',
        'resource_type' => 'User',
        'resource_id' => null,
        'resource_name' => "{$activatedCount} users",
        'description' => "Admin {$adminUser->name} bulk activated {$activatedCount} users",
        'ip_address' => request()->ip(),
        'user_agent' => request()->userAgent(),
    ]);

    return back()->with('success', "Activated {$activatedCount} users");
})->name('admin.users.bulk-activate');

Route::delete('/users/bulk-delete', function () {
    $adminUser = Auth::user();

    if (! $adminUser->isSuperAdmin()) {
        return back()->with('error', 'Unauthorized: Only superadmins can delete users');
    }

    $userIds = request()->input('user_ids', []);
    if (empty($userIds)) {
        return back()->with('error', 'No users selected');
    }

    $deletedCount = 0;
    $skippedCount = 0;

    // Wrap bulk delete in transaction for atomicity
    \Illuminate\Support\Facades\DB::transaction(function () use ($userIds, &$deletedCount, &$skippedCount) {
        foreach ($userIds as $userId) {
            $user = \App\Models\User::find($userId);
            if (! $user || $user->id === 0 || $user->isSuperAdmin()) {
                $skippedCount++;

                continue;
            }

            $user->delete();
            $deletedCount++;
        }
    });

    // Log bulk action
    \App\Models\AuditLog::create([
        'user_id' => $adminUser->id,
        'team_id' => null,
        'action' => 'users_bulk_deleted',
        'resource_type' => 'User',
        'resource_id' => null,
        'resource_name' => "{$deletedCount} users",
        'description' => "Admin {$adminUser->name} bulk deleted {$deletedCount} users",
        'ip_address' => request()->ip(),
        'user_agent' => request()->userAgent(),
    ]);

    return back()->with('success', "Deleted {$deletedCount} users".($skippedCount > 0 ? " ({$skippedCount} skipped)" : ''));
})->name('admin.users.bulk-delete');

Route::get('/users/export', function () {
    $adminUser = Auth::user();

    if (! $adminUser->isSuperAdmin()) {
        return response()->json(['error' => 'Unauthorized'], 403);
    }

    // Get filter parameters
    $search = request()->input('search', '');
    $statusFilter = request()->input('status', 'all');

    // Build query
    $query = \App\Models\User::with(['teams'])
        ->withCount(['teams']);

    if ($search) {
        $query->where(function ($q) use ($search) {
            $q->where('name', 'ILIKE', "%{$search}%")
                ->orWhere('email', 'ILIKE', "%{$search}%");
        });
    }

    if ($statusFilter !== 'all') {
        $query->where('status', $statusFilter);
    }

    $users = $query->orderBy('created_at', 'desc')->get();

    // Generate CSV with UTF-8 BOM for Excel compatibility
    $bom = "\xEF\xBB\xBF"; // UTF-8 BOM for Excel to recognize encoding and delimiter
    $csv = $bom."ID,Name,Email,Status,Teams,Created At,Last Login\n";
    foreach ($users as $user) {
        $status = $user->status ?? 'active';
        if ($status === 'active' && is_null($user->email_verified_at)) {
            $status = 'pending';
        }
        $csv .= sprintf(
            "%d,\"%s\",\"%s\",%s,%d,%s,%s\n",
            $user->id,
            str_replace('"', '""', $user->name),
            str_replace('"', '""', $user->email),
            $status,
            $user->teams_count,
            $user->created_at->format('Y-m-d H:i:s'),
            $user->last_login_at?->format('Y-m-d H:i:s') ?? 'Never'
        );
    }

    // Log export
    \App\Models\AuditLog::create([
        'user_id' => $adminUser->id,
        'team_id' => null,
        'action' => 'users_exported',
        'resource_type' => 'User',
        'resource_id' => null,
        'resource_name' => "{$users->count()} users",
        'description' => "Admin {$adminUser->name} exported {$users->count()} users to CSV",
        'ip_address' => request()->ip(),
        'user_agent' => request()->userAgent(),
    ]);

    return response($csv, 200, [
        'Content-Type' => 'text/csv',
        'Content-Disposition' => 'attachment; filename="users-export-'.now()->format('Y-m-d').'.csv"',
    ]);
})->name('admin.users.export');

// User resource management for deletion
Route::get('/users/{id}/resources', function (int $id) {
    $adminUser = Auth::user();

    if (! $adminUser->isSuperAdmin()) {
        return back()->with('error', 'Unauthorized: Only superadmins can manage user resources');
    }

    $user = \App\Models\User::findOrFail($id);

    // Cannot delete root user
    if ($user->id === 0 || $user->isSuperAdmin()) {
        return back()->with('error', 'Cannot delete root user or superadmins');
    }

    $transferService = app(\App\Services\Transfer\UserResourceTransferService::class);

    // Get resource tree
    $resourceTree = $transferService->getResourceTree($user);

    // Get transfer destinations
    $destinations = $transferService->getTransferDestinations($user);

    // Check if can safely delete
    $deleteCheck = $transferService->canSafelyDeleteUser($user);

    return Inertia::render('Admin/Users/DeleteWithTransfer', [
        'user' => [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'created_at' => $user->created_at->toISOString(),
        ],
        'resourceTree' => $resourceTree,
        'destinations' => [
            'teams' => $destinations['teams']->map(fn ($t) => [
                'id' => $t->id,
                'name' => $t->name,
                'personal_team' => $t->personal_team,
                'members_count' => $t->members()->count(),
            ]),
            'users' => $destinations['users']->map(fn ($u) => [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
            ]),
        ],
        'deleteCheck' => $deleteCheck,
    ]);
})->name('admin.users.resources');

Route::post('/users/{id}/transfer-and-delete', function (int $id) {
    $adminUser = Auth::user();

    if (! $adminUser->isSuperAdmin()) {
        return back()->with('error', 'Unauthorized: Only superadmins can delete users');
    }

    $user = \App\Models\User::findOrFail($id);

    // Cannot delete root user or superadmins
    if ($user->id === 0 || $user->isSuperAdmin()) {
        return back()->with('error', 'Cannot delete root user or superadmins');
    }

    $transferType = request()->input('transfer_type'); // 'team', 'user', 'archive', 'delete_all'
    $targetTeamId = request()->input('target_team_id');
    $targetUserId = request()->input('target_user_id');
    $reason = request()->input('reason', 'User account deletion');

    $transferService = app(\App\Services\Transfer\UserResourceTransferService::class);

    try {
        $transfers = collect();

        switch ($transferType) {
            case 'team':
                if (! $targetTeamId) {
                    return back()->with('error', 'Target team is required');
                }
                $targetTeam = \App\Models\Team::findOrFail($targetTeamId);
                $transfers = $transferService->transferAllToTeam($user, $targetTeam, $adminUser, $reason);
                break;

            case 'user':
                if (! $targetUserId) {
                    return back()->with('error', 'Target user is required');
                }
                $targetUser = \App\Models\User::findOrFail($targetUserId);
                $transfers = $transferService->transferOwnershipToUser($user, $targetUser, $adminUser, $reason);
                break;

            case 'archive':
                $transfers = $transferService->archiveUserResources($user, $adminUser);
                break;

            case 'delete_all':
                // Just proceed with deletion, resources will be cascade deleted
                break;

            default:
                return back()->with('error', 'Invalid transfer type');
        }

        // Store user info before deletion
        $userName = $user->name;
        $userEmail = $user->email;

        // Delete the user
        $user->delete();

        // Log the deletion
        \App\Models\AuditLog::create([
            'user_id' => $adminUser->id,
            'team_id' => null,
            'action' => 'user_deleted_with_transfer',
            'resource_type' => 'User',
            'resource_id' => $id,
            'resource_name' => $userName,
            'description' => "Admin {$adminUser->name} deleted user {$userName} ({$userEmail}). Transfer type: {$transferType}. Transfers: {$transfers->count()}",
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);

        return redirect()->route('admin.users.index')
            ->with('success', "User {$userName} deleted successfully. {$transfers->count()} resources transferred.");

    } catch (\Exception $e) {
        return back()->with('error', 'Failed to delete user: '.$e->getMessage());
    }
})->name('admin.users.transfer-and-delete');

Route::post('/users/{id}/platform-role', function (int $id, \Illuminate\Http\Request $request) {
    $adminUser = Auth::user();

    if (! $adminUser->isSuperAdmin()) {
        return back()->with('error', 'Unauthorized: Only superadmins can change platform roles');
    }

    $request->validate([
        'platform_role' => 'required|string|in:owner,admin,member',
    ]);

    $user = \App\Models\User::findOrFail($id);

    // Cannot change root user role
    if ($user->id === 0) {
        return back()->with('error', 'Cannot change root user role');
    }

    // Cannot demote yourself
    if ($user->id === $adminUser->id && $request->input('platform_role') === 'member') {
        return back()->with('error', 'Cannot demote yourself');
    }

    $oldRole = $user->getPlatformRole();
    $user->update(['platform_role' => $request->input('platform_role')]);

    return back()->with('success', "Platform role changed from {$oldRole} to {$request->input('platform_role')}");
})->name('admin.users.platform-role');
