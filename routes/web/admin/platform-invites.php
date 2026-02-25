<?php

/**
 * Admin Platform Invites routes
 *
 * Platform-level invitations that allow new users to register
 * when public registration is disabled.
 */

use App\Models\PlatformInvite;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/platform-invites', function () {
    $invites = PlatformInvite::with('creator')
        ->latest()
        ->paginate(50)
        ->through(fn (PlatformInvite $invite) => [ // @phpstan-ignore argument.unresolvableType
            'id' => $invite->id,
            'uuid' => $invite->uuid,
            'email' => $invite->email,
            'link' => $invite->getLink(),
            'created_by_name' => $invite->creator->name ?? 'Unknown',
            'is_valid' => $invite->isValid(),
            'used_at' => $invite->used_at?->toISOString(), // @phpstan-ignore method.nonObject
            'created_at' => $invite->created_at?->toISOString(), // @phpstan-ignore method.nonObject
        ]);

    return Inertia::render('Admin/PlatformInvites/Index', [
        'invites' => $invites,
    ]);
})->name('admin.platform-invites.index');

Route::post('/platform-invites', function (Request $request) {
    $validated = $request->validate([
        'email' => ['required', 'email', 'max:255'],
    ]);

    $email = strtolower($validated['email']);

    // Check if user already exists
    if (\App\Models\User::where('email', $email)->exists()) {
        return back()->with('error', 'A user with this email already exists.');
    }

    // Check if there's already a pending valid invite for this email
    $existing = PlatformInvite::where('email', $email)
        ->whereNull('used_at')
        ->first();

    if ($existing && $existing->isValid()) {
        return back()->with('error', 'A valid platform invite for this email already exists.');
    }

    $invite = PlatformInvite::create([
        'email' => $email,
        'created_by' => auth()->id(),
    ]);

    return back()->with('success', 'Platform invite created. Share this link: '.$invite->getLink());
})->name('admin.platform-invites.create');

Route::delete('/platform-invites/{id}', function (int $id) {
    $invite = PlatformInvite::findOrFail($id);
    $invite->delete();

    return back()->with('success', 'Platform invite deleted.');
})->name('admin.platform-invites.delete');
