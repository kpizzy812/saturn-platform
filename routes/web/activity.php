<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

/*
|--------------------------------------------------------------------------
| Activity Routes
|--------------------------------------------------------------------------
|
| Routes for viewing team activity logs and audit trail.
|
*/

Route::get('/activity', function () {
    $activities = \App\Http\Controllers\Inertia\ActivityHelper::getTeamActivities(50);

    return Inertia::render('Activity/Index', [
        'activities' => $activities,
    ]);
})->name('activity.index');

Route::get('/activity/timeline', function () {
    $activities = \App\Http\Controllers\Inertia\ActivityHelper::getTeamActivities(100);

    return Inertia::render('Activity/Timeline', [
        'activities' => $activities,
        'currentPage' => 1,
        'totalPages' => 1,
    ]);
})->name('activity.timeline');

Route::get('/activity/project/{projectUuid}', function (string $projectUuid) {
    $project = \App\Models\Project::ownedByCurrentTeam()
        ->where('uuid', $projectUuid)
        ->first();

    if (! $project) {
        return Inertia::render('Activity/ProjectActivity', [
            'project' => null,
        ]);
    }

    $environments = $project->environments()->select('id', 'name', 'uuid')->get();

    $activities = \App\Http\Controllers\Inertia\ActivityHelper::getTeamActivities(50);

    return Inertia::render('Activity/ProjectActivity', [
        'project' => [
            'id' => $project->id,
            'uuid' => $project->uuid,
            'name' => $project->name,
            'description' => $project->description,
        ],
        'environments' => $environments,
        'activities' => $activities,
    ]);
})->name('activity.project');

Route::get('/activity/{uuid}', function (string $uuid) {
    $activity = \App\Http\Controllers\Inertia\ActivityHelper::getActivity($uuid);
    $related = \App\Http\Controllers\Inertia\ActivityHelper::getRelatedActivities($uuid);

    return Inertia::render('Activity/Show', [
        'activity' => $activity,
        'relatedActivities' => $related,
    ]);
})->name('activity.show');
