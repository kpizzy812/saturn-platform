<?php

/**
 * Admin Templates routes
 *
 * Application template management including listing, creation, viewing,
 * updating, deletion, and duplication.
 */

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

// List all templates
Route::get('/templates', function (Request $request) {
    $query = \App\Models\ApplicationTemplate::query();

    // Search
    if ($request->filled('search')) {
        $search = $request->input('search');
        $query->where(function ($q) use ($search) {
            $q->where('name', 'ilike', "%{$search}%")
                ->orWhere('description', 'ilike', "%{$search}%");
        });
    }

    // Filter by category
    if ($request->filled('category') && $request->input('category') !== 'all') {
        $query->where('category', $request->input('category'));
    }

    // Filter by official
    if ($request->boolean('official_only')) {
        $query->where('is_official', true);
    }

    // Sorting
    $sortBy = $request->input('sort', 'name');
    $sortOrder = $request->input('order', 'asc');
    $allowedSorts = ['name', 'category', 'usage_count', 'rating', 'created_at'];
    if (in_array($sortBy, $allowedSorts)) {
        $query->orderBy($sortBy, $sortOrder === 'desc' ? 'desc' : 'asc');
    }

    $templates = $query->with('createdBy')
        ->paginate(24)
        ->through(function ($template) {
            return [
                'id' => $template->id,
                'uuid' => $template->uuid,
                'name' => $template->name,
                'slug' => $template->slug,
                'description' => $template->description,
                'category' => $template->category,
                'icon' => $template->icon,
                'is_official' => $template->is_official,
                'is_public' => $template->is_public,
                'version' => $template->version,
                'tags' => $template->tags ?? [],
                'usage_count' => $template->usage_count,
                'rating' => $template->rating,
                'rating_count' => $template->rating_count,
                'created_by' => $template->createdBy?->name,
                'created_at' => $template->created_at,
            ];
        });

    $categories = \App\Models\ApplicationTemplate::categories();

    return Inertia::render('Admin/Templates/Index', [
        'templates' => $templates,
        'categories' => $categories,
        'filters' => [
            'search' => $request->input('search', ''),
            'category' => $request->input('category', 'all'),
            'official_only' => $request->boolean('official_only'),
            'sort' => $sortBy,
            'order' => $sortOrder,
        ],
    ]);
})->name('admin.templates.index');

// Show create template form
Route::get('/templates/create', function () {
    $categories = \App\Models\ApplicationTemplate::categories();

    return Inertia::render('Admin/Templates/Create', [
        'categories' => $categories,
    ]);
})->name('admin.templates.create');

// Store new template
Route::post('/templates', function (Request $request) {
    $validated = $request->validate([
        'name' => 'required|string|max:255',
        'description' => 'nullable|string|max:1000',
        'category' => 'required|string|in:'.implode(',', array_keys(\App\Models\ApplicationTemplate::categories())),
        'icon' => 'nullable|string|max:255',
        'is_official' => 'boolean',
        'is_public' => 'boolean',
        'tags' => 'nullable|array',
        'tags.*' => 'string|max:50',
        'config' => 'required|array',
        'config.build_pack' => 'required|string|in:nixpacks,static,dockerfile,dockercompose',
        'config.ports_exposes' => 'nullable|string',
        'config.install_command' => 'nullable|string',
        'config.build_command' => 'nullable|string',
        'config.start_command' => 'nullable|string',
        'config.base_directory' => 'nullable|string',
        'config.publish_directory' => 'nullable|string',
        'config.environment_variables' => 'nullable|array',
    ]);

    $template = \App\Models\ApplicationTemplate::create([
        'name' => $validated['name'],
        'description' => $validated['description'] ?? null,
        'category' => $validated['category'],
        'icon' => $validated['icon'] ?? null,
        'is_official' => $validated['is_official'] ?? false,
        'is_public' => $validated['is_public'] ?? true,
        'tags' => $validated['tags'] ?? [],
        'config' => $validated['config'],
        'created_by' => auth()->id(),
    ]);

    \App\Models\AuditLog::create([
        'action' => 'template_created',
        'resource_type' => 'ApplicationTemplate',
        'resource_id' => $template->id,
        'resource_name' => $template->name,
        'user_id' => auth()->id(),
        'team_id' => currentTeam()?->id,
        'ip_address' => $request->ip(),
        'user_agent' => $request->userAgent(),
        'description' => "Created application template: {$template->name}",
    ]);

    return redirect()->route('admin.templates.index')
        ->with('success', 'Template created successfully.');
})->name('admin.templates.store');

// Show template details
Route::get('/templates/{uuid}', function (string $uuid) {
    $template = \App\Models\ApplicationTemplate::where('uuid', $uuid)->firstOrFail();

    return Inertia::render('Admin/Templates/Show', [
        'template' => [
            'id' => $template->id,
            'uuid' => $template->uuid,
            'name' => $template->name,
            'slug' => $template->slug,
            'description' => $template->description,
            'category' => $template->category,
            'icon' => $template->icon,
            'is_official' => $template->is_official,
            'is_public' => $template->is_public,
            'version' => $template->version,
            'tags' => $template->tags ?? [],
            'config' => $template->config,
            'usage_count' => $template->usage_count,
            'rating' => $template->rating,
            'rating_count' => $template->rating_count,
            'created_by' => $template->createdBy?->name,
            'created_at' => $template->created_at,
            'updated_at' => $template->updated_at,
        ],
        'categories' => \App\Models\ApplicationTemplate::categories(),
    ]);
})->name('admin.templates.show');

// Update template
Route::put('/templates/{uuid}', function (Request $request, string $uuid) {
    $template = \App\Models\ApplicationTemplate::where('uuid', $uuid)->firstOrFail();

    $validated = $request->validate([
        'name' => 'required|string|max:255',
        'description' => 'nullable|string|max:1000',
        'category' => 'required|string|in:'.implode(',', array_keys(\App\Models\ApplicationTemplate::categories())),
        'icon' => 'nullable|string|max:255',
        'is_official' => 'boolean',
        'is_public' => 'boolean',
        'tags' => 'nullable|array',
        'tags.*' => 'string|max:50',
        'config' => 'required|array',
    ]);

    $template->update([
        'name' => $validated['name'],
        'description' => $validated['description'] ?? null,
        'category' => $validated['category'],
        'icon' => $validated['icon'] ?? null,
        'is_official' => $validated['is_official'] ?? false,
        'is_public' => $validated['is_public'] ?? true,
        'tags' => $validated['tags'] ?? [],
        'config' => $validated['config'],
    ]);

    \App\Models\AuditLog::create([
        'action' => 'template_updated',
        'resource_type' => 'ApplicationTemplate',
        'resource_id' => $template->id,
        'resource_name' => $template->name,
        'user_id' => auth()->id(),
        'team_id' => currentTeam()?->id,
        'ip_address' => $request->ip(),
        'user_agent' => $request->userAgent(),
        'description' => "Updated application template: {$template->name}",
    ]);

    return back()->with('success', 'Template updated successfully.');
})->name('admin.templates.update');

// Delete template
Route::delete('/templates/{uuid}', function (Request $request, string $uuid) {
    $template = \App\Models\ApplicationTemplate::where('uuid', $uuid)->firstOrFail();

    $templateName = $template->name;

    \App\Models\AuditLog::create([
        'action' => 'template_deleted',
        'resource_type' => 'ApplicationTemplate',
        'resource_id' => $template->id,
        'resource_name' => $templateName,
        'user_id' => auth()->id(),
        'team_id' => currentTeam()?->id,
        'ip_address' => $request->ip(),
        'user_agent' => $request->userAgent(),
        'description' => "Deleted application template: {$templateName}",
    ]);

    $template->delete();

    return redirect()->route('admin.templates.index')
        ->with('success', 'Template deleted successfully.');
})->name('admin.templates.destroy');

// Duplicate template
Route::post('/templates/{uuid}/duplicate', function (Request $request, string $uuid) {
    $original = \App\Models\ApplicationTemplate::where('uuid', $uuid)->firstOrFail();

    $newTemplate = $original->replicate();
    $newTemplate->name = $original->name.' (Copy)';
    $newTemplate->slug = null; // Will be regenerated
    $newTemplate->uuid = null; // Will be regenerated
    $newTemplate->is_official = false;
    $newTemplate->usage_count = 0;
    $newTemplate->rating = null;
    $newTemplate->rating_count = 0;
    $newTemplate->created_by = auth()->id();
    $newTemplate->save();

    \App\Models\AuditLog::create([
        'action' => 'template_duplicated',
        'resource_type' => 'ApplicationTemplate',
        'resource_id' => $newTemplate->id,
        'resource_name' => $newTemplate->name,
        'user_id' => auth()->id(),
        'team_id' => currentTeam()?->id,
        'ip_address' => $request->ip(),
        'user_agent' => $request->userAgent(),
        'description' => "Duplicated template from: {$original->name}",
    ]);

    return redirect()->route('admin.templates.show', $newTemplate->uuid)
        ->with('success', 'Template duplicated successfully.');
})->name('admin.templates.duplicate');
