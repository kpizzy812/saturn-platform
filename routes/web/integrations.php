<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

/*
|--------------------------------------------------------------------------
| Integrations Routes
|--------------------------------------------------------------------------
|
| Routes for managing webhooks and other integrations.
|
*/

Route::get('/integrations/webhooks', function () {
    $team = auth()->user()->currentTeam();
    $webhooks = $team->webhooks()
        ->with(['deliveries' => function ($query) {
            $query->limit(5);
        }])
        ->orderBy('created_at', 'desc')
        ->get();

    return Inertia::render('Integrations/Webhooks', [
        'webhooks' => $webhooks,
        'availableEvents' => \App\Models\TeamWebhook::availableEvents(),
    ]);
})->name('integrations.webhooks');

Route::post('/integrations/webhooks', function (Request $request) {
    if (! in_array(auth()->user()->role(), ['owner', 'admin'])) {
        abort(403, 'You do not have permission to create webhooks.');
    }

    $team = auth()->user()->currentTeam();

    $validated = $request->validate([
        'name' => 'required|string|max:255',
        'url' => 'required|url|max:2048',
        'events' => 'required|array|min:1',
    ]);

    $team->webhooks()->create($validated);

    return redirect()->back()->with('success', 'Webhook created successfully.');
})->name('integrations.webhooks.store');

Route::put('/integrations/webhooks/{uuid}', function (Request $request, string $uuid) {
    if (! in_array(auth()->user()->role(), ['owner', 'admin'])) {
        abort(403, 'You do not have permission to update webhooks.');
    }

    $team = auth()->user()->currentTeam();
    $webhook = $team->webhooks()->where('uuid', $uuid)->firstOrFail();

    $validated = $request->validate([
        'name' => 'sometimes|string|max:255',
        'url' => 'sometimes|url|max:2048',
        'events' => 'sometimes|array|min:1',
        'enabled' => 'sometimes|boolean',
    ]);

    $webhook->update($validated);

    return redirect()->back()->with('success', 'Webhook updated successfully.');
})->name('integrations.webhooks.update');

Route::delete('/integrations/webhooks/{uuid}', function (string $uuid) {
    if (! in_array(auth()->user()->role(), ['owner', 'admin'])) {
        abort(403, 'You do not have permission to delete webhooks.');
    }

    $team = auth()->user()->currentTeam();
    $webhook = $team->webhooks()->where('uuid', $uuid)->firstOrFail();

    $webhook->delete();

    return redirect()->back()->with('success', 'Webhook deleted successfully.');
})->name('integrations.webhooks.destroy');

Route::post('/integrations/webhooks/{uuid}/toggle', function (string $uuid) {
    if (! in_array(auth()->user()->role(), ['owner', 'admin'])) {
        abort(403, 'You do not have permission to toggle webhooks.');
    }

    $team = auth()->user()->currentTeam();
    $webhook = $team->webhooks()->where('uuid', $uuid)->firstOrFail();

    $webhook->update(['enabled' => ! $webhook->enabled]);

    $status = $webhook->enabled ? 'enabled' : 'disabled';

    return redirect()->back()->with('success', "Webhook {$status} successfully.");
})->name('integrations.webhooks.toggle');

Route::post('/integrations/webhooks/{uuid}/test', function (string $uuid) {
    if (! in_array(auth()->user()->role(), ['owner', 'admin'])) {
        abort(403, 'You do not have permission to test webhooks.');
    }

    $team = auth()->user()->currentTeam();
    $webhook = $team->webhooks()->where('uuid', $uuid)->firstOrFail();

    $delivery = \App\Models\WebhookDelivery::create([
        'team_webhook_id' => $webhook->id,
        'event' => 'test.event',
        'status' => 'pending',
        'payload' => [
            'event' => 'test.event',
            'message' => 'This is a test webhook delivery from Saturn.',
            'timestamp' => now()->toIso8601String(),
        ],
    ]);

    \App\Jobs\SendTeamWebhookJob::dispatch($webhook, $delivery);

    return redirect()->back()->with('success', 'Test webhook queued for delivery.');
})->name('integrations.webhooks.test');

Route::post('/integrations/webhooks/{uuid}/deliveries/{deliveryUuid}/retry', function (string $uuid, string $deliveryUuid) {
    if (! in_array(auth()->user()->role(), ['owner', 'admin'])) {
        abort(403, 'You do not have permission to retry webhook deliveries.');
    }

    $team = auth()->user()->currentTeam();
    $webhook = $team->webhooks()->where('uuid', $uuid)->firstOrFail();
    $delivery = $webhook->deliveries()->where('uuid', $deliveryUuid)->firstOrFail();

    $delivery->update(['status' => 'pending']);
    \App\Jobs\SendTeamWebhookJob::dispatch($webhook, $delivery);

    return redirect()->back()->with('success', 'Retry queued for delivery.');
})->name('integrations.webhooks.retry');
