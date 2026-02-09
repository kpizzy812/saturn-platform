<?php

/**
 * Admin Teams routes
 *
 * Team management including listing, viewing, member management, and deletion.
 */

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/teams', function (\Illuminate\Http\Request $request) {
    $query = \App\Models\Team::withCount(['members', 'projects', 'servers']);

    // Search filter
    if ($search = $request->get('search')) {
        $query->where(function ($q) use ($search) {
            $q->where('name', 'like', "%{$search}%")
                ->orWhere('description', 'like', "%{$search}%");
        });
    }

    $teams = $query->latest()
        ->paginate(50)
        ->through(function ($team) {
            return [
                'id' => $team->id,
                'name' => $team->name,
                'description' => $team->description,
                'personal_team' => $team->personal_team,
                'members_count' => $team->members_count,
                'projects_count' => $team->projects_count,
                'servers_count' => $team->servers_count,
                'created_at' => $team->created_at,
                'updated_at' => $team->updated_at,
            ];
        });

    return Inertia::render('Admin/Teams/Index', [
        'teams' => $teams,
        'filters' => [
            'search' => $request->get('search'),
        ],
    ]);
})->name('admin.teams.index');

Route::get('/teams/{id}', function (int $id) {
    // Fetch specific team with all relationships
    $team = \App\Models\Team::with(['members', 'servers.settings', 'projects'])
        ->withCount(['members', 'servers', 'projects'])
        ->findOrFail($id);

    return Inertia::render('Admin/Teams/Show', [
        'team' => [
            'id' => $team->id,
            'name' => $team->name,
            'description' => $team->description,
            'personal_team' => $team->personal_team,
            'created_at' => $team->created_at,
            'updated_at' => $team->updated_at,
            'members' => $team->members->map(function ($member) {
                return [
                    'id' => $member->id,
                    'name' => $member->name,
                    'email' => $member->email,
                    'role' => $member->pivot->role ?? 'member',
                    'created_at' => $member->created_at,
                ];
            }),
            'servers' => $team->servers->map(function ($server) {
                return [
                    'id' => $server->id,
                    'uuid' => $server->uuid,
                    'name' => $server->name,
                    'ip' => $server->ip,
                    'is_reachable' => $server->settings?->is_reachable ?? false,
                ];
            }),
            'projects' => $team->projects->map(function ($project) {
                return [
                    'id' => $project->id,
                    'uuid' => $project->uuid,
                    'name' => $project->name,
                    'environments_count' => $project->environments()->count(),
                ];
            }),
        ],
    ]);
})->name('admin.teams.show');

Route::post('/teams/{teamId}/members/{userId}/remove', function (int $teamId, int $userId) {
    $team = \App\Models\Team::findOrFail($teamId);
    $user = \App\Models\User::findOrFail($userId);

    try {
        // Use transaction with lock to prevent race condition on role check + detach
        \Illuminate\Support\Facades\DB::transaction(function () use ($team, $userId) {
            $member = $team->members()->where('user_id', $userId)->lockForUpdate()->first();
            if (! $member) {
                throw new \RuntimeException('User is not a member of this team');
            }
            if ($member->pivot?->role === 'owner') {
                throw new \RuntimeException('Cannot remove team owner');
            }
            $team->members()->detach($userId);
        });
    } catch (\RuntimeException $e) {
        return back()->with('error', $e->getMessage());
    }

    return back()->with('success', "Removed {$user->name} from team");
})->name('admin.teams.members.remove');

Route::post('/teams/{teamId}/members/{userId}/role', function (int $teamId, int $userId) {
    $team = \App\Models\Team::findOrFail($teamId);
    $newRole = request()->input('role');

    if (! in_array($newRole, ['owner', 'admin', 'developer', 'member', 'viewer'])) {
        return back()->with('error', 'Invalid role');
    }

    $team->members()->updateExistingPivot($userId, ['role' => $newRole]);

    return back()->with('success', 'Role updated successfully');
})->name('admin.teams.members.role');

Route::delete('/teams/{id}', function (int $id) {
    $team = \App\Models\Team::findOrFail($id);

    // Prevent deletion of root team (id=0) or personal teams
    if ($team->id === 0) {
        return back()->with('error', 'Cannot delete root team');
    }

    if ($team->personal_team) {
        return back()->with('error', 'Cannot delete personal teams');
    }

    $teamName = $team->name;
    $team->delete();

    return redirect()->route('admin.teams.index')->with('success', "Team '{$teamName}' deleted");
})->name('admin.teams.delete');
