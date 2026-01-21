<?php

namespace App\Http\Controllers\Inertia;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\Server;
use App\Models\Service;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

class ServiceController extends Controller
{
    /**
     * Display a listing of services.
     */
    public function index(): Response
    {
        $services = Service::ownedByCurrentTeam()->get();

        return Inertia::render('Services/Index', ['services' => $services]);
    }

    /**
     * Show the form for creating a new service.
     */
    public function create(): Response
    {
        return Inertia::render('Services/Create');
    }

    /**
     * Store a newly created service in storage.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'docker_compose_raw' => 'required|string',
            'project_uuid' => 'required|string',
            'environment_uuid' => 'required|string',
            'server_uuid' => 'required|string',
        ]);

        $project = Project::ownedByCurrentTeam()
            ->where('uuid', $validated['project_uuid'])
            ->firstOrFail();

        $environment = $project->environments()
            ->where('uuid', $validated['environment_uuid'])
            ->firstOrFail();

        $server = Server::ownedByCurrentTeam()
            ->where('uuid', $validated['server_uuid'])
            ->firstOrFail();

        $destination = $server->destinations()->first();
        if (! $destination) {
            return redirect()->back()->withErrors(['server_uuid' => 'Server has no destinations configured']);
        }

        $service = new Service();
        $service->name = $validated['name'];
        $service->description = $validated['description'] ?? null;
        $service->docker_compose_raw = $validated['docker_compose_raw'];
        $service->environment_id = $environment->id;
        $service->destination_id = $destination->id;
        $service->destination_type = $destination->getMorphClass();
        $service->server_id = $server->id;
        $service->save();

        try {
            $service->parse();
        } catch (\Exception $e) {
            Log::warning('Failed to parse docker-compose: '.$e->getMessage());
        }

        return redirect()->route('services.show', $service->uuid)
            ->with('success', 'Service created successfully');
    }

    /**
     * Display the specified service.
     */
    public function show(string $uuid): Response
    {
        $service = Service::ownedByCurrentTeam()
            ->where('uuid', $uuid)
            ->firstOrFail();

        return Inertia::render('Services/Show', [
            'service' => $service,
        ]);
    }

    /**
     * Display service metrics.
     */
    public function metrics(string $uuid): Response
    {
        $service = Service::ownedByCurrentTeam()
            ->where('uuid', $uuid)
            ->firstOrFail();

        return Inertia::render('Services/Metrics', [
            'service' => $service,
        ]);
    }

    /**
     * Display service build logs.
     */
    public function buildLogs(string $uuid): Response
    {
        $service = Service::ownedByCurrentTeam()
            ->where('uuid', $uuid)
            ->firstOrFail();

        return Inertia::render('Services/BuildLogs', [
            'service' => $service,
        ]);
    }

    /**
     * Display service domains.
     */
    public function domains(string $uuid): Response
    {
        $service = Service::ownedByCurrentTeam()
            ->where('uuid', $uuid)
            ->firstOrFail();

        return Inertia::render('Services/Domains', [
            'service' => $service,
        ]);
    }

    /**
     * Display service webhooks.
     */
    public function webhooks(string $uuid): Response
    {
        $service = Service::ownedByCurrentTeam()
            ->where('uuid', $uuid)
            ->firstOrFail();

        return Inertia::render('Services/Webhooks', [
            'service' => $service,
        ]);
    }

    /**
     * Display service deployments.
     */
    public function deployments(string $uuid): Response
    {
        $service = Service::ownedByCurrentTeam()
            ->where('uuid', $uuid)
            ->firstOrFail();

        return Inertia::render('Services/Deployments', [
            'service' => $service,
        ]);
    }

    /**
     * Display service logs.
     */
    public function logs(string $uuid): Response
    {
        $service = Service::ownedByCurrentTeam()
            ->where('uuid', $uuid)
            ->firstOrFail();

        return Inertia::render('Services/Logs', [
            'service' => $service,
        ]);
    }

    /**
     * Display service health checks.
     */
    public function healthChecks(string $uuid): Response
    {
        $service = Service::ownedByCurrentTeam()
            ->where('uuid', $uuid)
            ->firstOrFail();

        return Inertia::render('Services/HealthChecks', [
            'service' => $service,
        ]);
    }

    /**
     * Display service networking.
     */
    public function networking(string $uuid): Response
    {
        $service = Service::ownedByCurrentTeam()
            ->where('uuid', $uuid)
            ->firstOrFail();

        return Inertia::render('Services/Networking', [
            'service' => $service,
        ]);
    }

    /**
     * Display service scaling.
     */
    public function scaling(string $uuid): Response
    {
        $service = Service::ownedByCurrentTeam()
            ->where('uuid', $uuid)
            ->firstOrFail();

        return Inertia::render('Services/Scaling', [
            'service' => $service,
        ]);
    }

    /**
     * Display service rollbacks.
     */
    public function rollbacks(string $uuid): Response
    {
        $service = Service::ownedByCurrentTeam()
            ->where('uuid', $uuid)
            ->firstOrFail();

        return Inertia::render('Services/Rollbacks', [
            'service' => $service,
        ]);
    }

    /**
     * Display service settings.
     */
    public function settings(string $uuid): Response
    {
        $service = Service::ownedByCurrentTeam()
            ->where('uuid', $uuid)
            ->firstOrFail();

        return Inertia::render('Services/Settings', [
            'service' => $service,
        ]);
    }

    /**
     * Display service environment variables.
     */
    public function variables(string $uuid): Response
    {
        $service = Service::ownedByCurrentTeam()
            ->where('uuid', $uuid)
            ->firstOrFail();

        return Inertia::render('Services/Variables', [
            'service' => $service,
        ]);
    }
}
