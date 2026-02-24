<?php

namespace App\Http\Middleware;

use App\Services\Authorization\PermissionService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CanCreateResources
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = auth()->user();
        if (! $user) {
            abort(403, 'You do not have permission to create resources.');
        }

        $permissionService = app(PermissionService::class);

        // Check if user has any create permission
        $hasCreate = $permissionService->userHasAnyPermission($user, [
            'applications.create',
            'databases.create',
            'services.create',
            'servers.create',
            'projects.create',
        ]);

        if (! $hasCreate) {
            abort(403, 'You do not have permission to create resources.');
        }

        return $next($request);
    }
}
