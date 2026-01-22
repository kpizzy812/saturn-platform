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
            ->with(['environments.applications', 'environments.databases'])
            ->get();

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

        // Create default environment
        $project->environments()->create([
            'name' => 'production',
        ]);

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
                'environments.databases.destination.server',
                'environments.services',
            ])
            ->where('uuid', $uuid)
            ->firstOrFail();

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
