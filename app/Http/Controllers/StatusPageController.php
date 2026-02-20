<?php

namespace App\Http\Controllers;

use App\Models\InstanceSettings;
use App\Models\StatusPageResource;
use Inertia\Inertia;
use Inertia\Response;

class StatusPageController extends Controller
{
    /**
     * Public status page — no authentication required.
     * Only exposes display_name + status, no internal IPs/server names.
     */
    public function index(): Response|\Illuminate\Http\RedirectResponse
    {
        $settings = InstanceSettings::get();

        if (! $settings->is_status_page_enabled) {
            return redirect('/');
        }

        $title = $settings->status_page_title ?? $settings->instance_name ?? 'Saturn';
        $description = $settings->status_page_description ?? '';

        // Load visible resources grouped by group_name
        $resources = StatusPageResource::where('is_visible', true)
            ->orderBy('display_order')
            ->orderBy('group_name')
            ->get();

        $groups = [];
        $allStatuses = [];

        foreach ($resources as $spr) {
            $status = $spr->resolveStatus();
            $allStatuses[] = $status;

            $groupName = $spr->group_name ?? 'Services';

            if (! isset($groups[$groupName])) {
                $groups[$groupName] = [];
            }

            // Only expose safe public data
            $groups[$groupName][] = [
                'name' => $spr->display_name,
                'status' => $status,
            ];
        }

        $overallStatus = StatusPageResource::computeOverallStatus($allStatuses);

        return Inertia::render('StatusPage/Index', [
            'title' => $title,
            'description' => $description,
            'overallStatus' => $overallStatus,
            'groups' => $groups,
        ]);
    }
}
