<?php

namespace App\Http\Controllers\Inertia;

use App\Http\Controllers\Controller;
use App\Models\Project;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProjectController extends Controller
{
    /**
     * Display a listing of projects.
     */
    public function index(): Response
    {
        $projects = Project::ownedByCurrentTeam()
            ->with([
                'environments.applications',
                'environments.postgresqls',
                'environments.mysqls',
                'environments.mariadbs',
                'environments.mongodbs',
                'environments.redis',
                'environments.clickhouses',
                'environments.keydbs',
                'environments.dragonflies',
            ])
            ->get();

        // Compute databases attribute from individual DB type relationships
        foreach ($projects as $project) {
            foreach ($project->environments as $env) {
                $env->setAttribute('databases', $env->databases());
            }
        }

        return Inertia::render('Projects/Index', [
            'projects' => $projects,
        ]);
    }

    /**
     * Show the form for creating a new project.
     */
    public function create(): Response
    {
        return Inertia::render('Projects/Create');
    }

    /**
     * Store a newly created project in storage.
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
        ]);

        $project = Project::create([
            'name' => $request->name,
            'description' => $request->description,
            'team_id' => currentTeam()->id,
        ]);

        // Note: Default environments (development, uat, production) are created
        // automatically in Project::booted() - no need to create them here

        return redirect()->route('projects.show', $project->uuid);
    }

    /**
     * Display the specified project.
     */
    public function show(string $uuid): Response
    {
        $project = Project::ownedByCurrentTeam()
            ->with([
                'environments.applications.destination.server',
                'environments.postgresqls.destination.server',
                'environments.mysqls.destination.server',
                'environments.mariadbs.destination.server',
                'environments.mongodbs.destination.server',
                'environments.redis.destination.server',
                'environments.clickhouses.destination.server',
                'environments.keydbs.destination.server',
                'environments.dragonflies.destination.server',
                'environments.services',
            ])
            ->where('uuid', $uuid)
            ->firstOrFail();

        // Compute databases attribute from individual DB type relationships
        foreach ($project->environments as $env) {
            $env->setAttribute('databases', $env->databases());
        }

        return Inertia::render('Projects/Show', [
            'project' => $project,
        ]);
    }

    /**
     * Display project environments.
     */
    public function environments(string $uuid): Response
    {
        $project = Project::ownedByCurrentTeam()
            ->where('uuid', $uuid)
            ->firstOrFail();

        return Inertia::render('Projects/Environments', [
            'project' => [
                'id' => $project->id,
                'uuid' => $project->uuid,
                'name' => $project->name,
            ],
        ]);
    }
}
