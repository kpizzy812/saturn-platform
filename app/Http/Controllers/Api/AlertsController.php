<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Alert\StoreAlertRequest;
use App\Http\Requests\Api\Alert\UpdateAlertRequest;
use App\Models\Alert;
use App\Models\AlertHistory;
use Illuminate\Http\JsonResponse;

class AlertsController extends Controller
{
    /**
     * List all alerts for the current team
     */
    public function index(): JsonResponse
    {
        $teamId = getTeamIdFromToken();
        if (is_null($teamId)) {
            return invalidTokenResponse();
        }

        $alerts = Alert::where('team_id', $teamId)
            ->orderBy('created_at', 'desc')
            ->limit(100)
            ->get()
            ->map(fn (Alert $alert) => [
                'id' => $alert->id,
                'uuid' => $alert->uuid,
                'name' => $alert->name,
                'metric' => $alert->metric,
                'condition' => $alert->condition,
                'threshold' => $alert->threshold,
                'duration' => $alert->duration,
                'enabled' => $alert->enabled,
                'channels' => $alert->channels ?? [],
                'triggered_count' => $alert->triggered_count,
                'last_triggered_at' => $alert->last_triggered_at ? $alert->last_triggered_at->toISOString() : null,
                'created_at' => $alert->created_at->toISOString(),
                'updated_at' => $alert->updated_at->toISOString(),
            ]);

        return response()->json($alerts);
    }

    /**
     * Get a specific alert by UUID
     */
    public function show(string $uuid): JsonResponse
    {
        $teamId = getTeamIdFromToken();
        if (is_null($teamId)) {
            return invalidTokenResponse();
        }

        $alert = Alert::where('team_id', $teamId)
            ->where('uuid', $uuid)
            ->first();

        if (! $alert) {
            return response()->json(['message' => 'Alert not found.'], 404);
        }

        /** @var \Illuminate\Database\Eloquent\Collection<int, AlertHistory> $histories */
        $histories = $alert->histories()
            ->orderByDesc('triggered_at')
            ->limit(50)
            ->get();
        $history = $histories->map(fn (AlertHistory $h) => [
            'id' => $h->id,
            'triggered_at' => $h->triggered_at->toISOString(),
            'resolved_at' => $h->resolved_at ? $h->resolved_at->toISOString() : null,
            'value' => $h->value,
            'status' => $h->status,
        ]);

        return response()->json([
            'id' => $alert->id,
            'uuid' => $alert->uuid,
            'name' => $alert->name,
            'metric' => $alert->metric,
            'condition' => $alert->condition,
            'threshold' => $alert->threshold,
            'duration' => $alert->duration,
            'enabled' => $alert->enabled,
            'channels' => $alert->channels ?? [],
            'triggered_count' => $alert->triggered_count,
            'last_triggered_at' => $alert->last_triggered_at ? $alert->last_triggered_at->toISOString() : null,
            'created_at' => $alert->created_at->toISOString(),
            'updated_at' => $alert->updated_at->toISOString(),
            'history' => $history,
        ]);
    }

    /**
     * Create a new alert
     */
    public function store(StoreAlertRequest $request): JsonResponse
    {
        $teamId = getTeamIdFromToken();
        if (is_null($teamId)) {
            return invalidTokenResponse();
        }

        $alert = new Alert($request->validated());
        $alert->team_id = $teamId;
        if (! $request->has('enabled')) {
            $alert->enabled = true;
        }
        $alert->save();

        return response()->json([
            'message' => 'Alert created successfully.',
            'alert' => [
                'id' => $alert->id,
                'uuid' => $alert->uuid,
                'name' => $alert->name,
                'metric' => $alert->metric,
                'condition' => $alert->condition,
                'threshold' => $alert->threshold,
                'duration' => $alert->duration,
                'enabled' => $alert->enabled,
                'channels' => $alert->channels ?? [],
            ],
        ], 201);
    }

    /**
     * Update an existing alert
     */
    public function update(UpdateAlertRequest $request, string $uuid): JsonResponse
    {
        $teamId = getTeamIdFromToken();
        if (is_null($teamId)) {
            return invalidTokenResponse();
        }

        $alert = Alert::where('team_id', $teamId)
            ->where('uuid', $uuid)
            ->first();

        if (! $alert) {
            return response()->json(['message' => 'Alert not found.'], 404);
        }

        $alert->update($request->validated());

        return response()->json([
            'message' => 'Alert updated successfully.',
            'alert' => [
                'id' => $alert->id,
                'uuid' => $alert->uuid,
                'name' => $alert->name,
                'metric' => $alert->metric,
                'condition' => $alert->condition,
                'threshold' => $alert->threshold,
                'duration' => $alert->duration,
                'enabled' => $alert->enabled,
                'channels' => $alert->channels ?? [],
            ],
        ]);
    }

    /**
     * Delete an alert
     */
    public function destroy(string $uuid): JsonResponse
    {
        $teamId = getTeamIdFromToken();
        if (is_null($teamId)) {
            return invalidTokenResponse();
        }

        $alert = Alert::where('team_id', $teamId)
            ->where('uuid', $uuid)
            ->first();

        if (! $alert) {
            return response()->json(['message' => 'Alert not found.'], 404);
        }

        $alert->delete();

        return response()->json(['message' => 'Alert deleted successfully.']);
    }
}
