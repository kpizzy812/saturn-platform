<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Application;
use App\Models\Environment;
use App\Models\ResourceLink;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class ResourceLinkController extends Controller
{
    private array $allowedTargetTypes = [
        'postgresql' => \App\Models\StandalonePostgresql::class,
        'mysql' => \App\Models\StandaloneMysql::class,
        'mariadb' => \App\Models\StandaloneMariadb::class,
        'redis' => \App\Models\StandaloneRedis::class,
        'keydb' => \App\Models\StandaloneKeydb::class,
        'dragonfly' => \App\Models\StandaloneDragonfly::class,
        'mongodb' => \App\Models\StandaloneMongodb::class,
        'clickhouse' => \App\Models\StandaloneClickhouse::class,
        'application' => \App\Models\Application::class,
    ];

    #[OA\Get(
        summary: 'List Resource Links',
        description: 'List all resource links for an environment.',
        path: '/environments/{environment_uuid}/links',
        operationId: 'list-resource-links',
        security: [['bearerAuth' => []]],
        tags: ['Resource Links'],
        parameters: [
            new OA\Parameter(name: 'environment_uuid', in: 'path', required: true, description: 'Environment UUID', schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'List of resource links'),
            new OA\Response(response: 401, ref: '#/components/responses/401'),
            new OA\Response(response: 404, description: 'Environment not found.'),
        ]
    )]
    public function index(string $environment_uuid)
    {
        $teamId = getTeamIdFromToken();
        if (is_null($teamId)) {
            return invalidTokenResponse();
        }

        $environment = Environment::whereUuid($environment_uuid)
            ->whereHas('project', fn ($q) => $q->where('team_id', $teamId))
            ->first();

        if (! $environment) {
            return response()->json(['message' => 'Environment not found.'], 404);
        }

        $links = ResourceLink::where('environment_id', $environment->id)
            ->with(['source', 'target'])
            ->get()
            ->map(fn ($link) => $this->formatLink($link));

        return response()->json($links);
    }

    #[OA\Post(
        summary: 'Create Resource Link',
        description: 'Create a new resource link between an application and a database or another application.',
        path: '/environments/{environment_uuid}/links',
        operationId: 'create-resource-link',
        security: [['bearerAuth' => []]],
        tags: ['Resource Links'],
        parameters: [
            new OA\Parameter(name: 'environment_uuid', in: 'path', required: true, description: 'Environment UUID', schema: new OA\Schema(type: 'string')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['source_id', 'target_type', 'target_id'],
                properties: [
                    new OA\Property(property: 'source_id', type: 'integer', description: 'Source Application ID'),
                    new OA\Property(property: 'target_type', type: 'string', description: 'Target type (postgresql, mysql, redis, application, etc.)'),
                    new OA\Property(property: 'target_id', type: 'integer', description: 'Target resource ID'),
                    new OA\Property(property: 'inject_as', type: 'string', nullable: true, description: 'Custom env variable name'),
                    new OA\Property(property: 'auto_inject', type: 'boolean', description: 'Auto-inject on deploy'),
                    new OA\Property(property: 'use_external_url', type: 'boolean', description: 'Use external FQDN instead of internal Docker URL (app-to-app only)'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Resource link created'),
            new OA\Response(response: 400, description: 'Validation error'),
            new OA\Response(response: 401, ref: '#/components/responses/401'),
            new OA\Response(response: 404, description: 'Environment, application, or target not found.'),
        ]
    )]
    public function store(Request $request, string $environment_uuid)
    {
        $teamId = getTeamIdFromToken();
        if (is_null($teamId)) {
            return invalidTokenResponse();
        }

        $environment = Environment::whereUuid($environment_uuid)
            ->whereHas('project', fn ($q) => $q->where('team_id', $teamId))
            ->first();

        if (! $environment) {
            return response()->json(['message' => 'Environment not found.'], 404);
        }

        $validated = $request->validate([
            'source_id' => 'required|integer',
            'target_type' => 'required|string|in:'.implode(',', array_keys($this->allowedTargetTypes)),
            'target_id' => 'required|integer',
            'inject_as' => 'nullable|string|max:255',
            'auto_inject' => 'boolean',
            'use_external_url' => 'boolean',
        ]);

        // Verify source application exists in this environment
        $application = Application::where('id', $validated['source_id'])
            ->where('environment_id', $environment->id)
            ->first();

        if (! $application) {
            return response()->json(['message' => 'Application not found in this environment.'], 404);
        }

        $targetClass = $this->allowedTargetTypes[$validated['target_type']];

        // Prevent self-linking for application targets
        if ($targetClass === Application::class && $validated['source_id'] === $validated['target_id']) {
            return response()->json(['message' => 'Cannot link an application to itself.'], 400);
        }

        // Verify target exists in this environment
        $target = $targetClass::where('id', $validated['target_id'])
            ->where('environment_id', $environment->id)
            ->first();

        if (! $target) {
            $targetLabel = $targetClass === Application::class ? 'Application' : 'Database';

            return response()->json(['message' => "{$targetLabel} not found in this environment."], 404);
        }

        // For app-to-app links, default to external URL (browser needs FQDN, not Docker DNS)
        $useExternalUrl = $validated['use_external_url'] ?? ($targetClass === Application::class);
        if ($targetClass === Application::class && ! $useExternalUrl) {
            $sourceServer = $application->destination?->server;
            $targetServer = $target->destination?->server;

            if ($sourceServer && $targetServer && $sourceServer->id !== $targetServer->id) {
                // Apps on different servers — internal Docker URL won't work
                if (! $target->fqdn) {
                    return response()->json([
                        'message' => 'Cannot link to an application on a different server without a configured FQDN (domain). Set a domain on the target application first, or deploy both apps on the same server.',
                    ], 400);
                }
                $useExternalUrl = true;
            }
        }

        // Check if link already exists
        $existingLink = ResourceLink::where('source_type', Application::class)
            ->where('source_id', $application->id)
            ->where('target_type', $targetClass)
            ->where('target_id', $target->id)
            ->first();

        if ($existingLink) {
            return response()->json(['message' => 'Link already exists.', 'link' => $this->formatLink($existingLink)], 200);
        }

        // Create the forward link
        $link = ResourceLink::create([
            'environment_id' => $environment->id,
            'source_type' => Application::class,
            'source_id' => $application->id,
            'target_type' => $targetClass,
            'target_id' => $target->id,
            'inject_as' => $validated['inject_as'] ?? null,
            'auto_inject' => $validated['auto_inject'] ?? true,
            'use_external_url' => $useExternalUrl,
        ]);

        // Immediately inject if auto_inject is enabled
        if ($link->auto_inject) {
            $application->autoInjectDatabaseUrl();
        }

        // For app-to-app links, auto-create reverse link (bidirectional)
        $reverseLink = null;
        if ($targetClass === Application::class) {
            $existingReverse = ResourceLink::where('source_type', Application::class)
                ->where('source_id', $target->id)
                ->where('target_type', Application::class)
                ->where('target_id', $application->id)
                ->first();

            if (! $existingReverse) {
                $reverseLink = ResourceLink::create([
                    'environment_id' => $environment->id,
                    'source_type' => Application::class,
                    'source_id' => $target->id,
                    'target_type' => Application::class,
                    'target_id' => $application->id,
                    'inject_as' => null,
                    'auto_inject' => $validated['auto_inject'] ?? true,
                    'use_external_url' => $useExternalUrl,
                ]);

                if ($reverseLink->auto_inject) {
                    $target->autoInjectDatabaseUrl();
                }
            }
        }

        // Return array for app-to-app (both directions), single object for db links
        $link->load(['source', 'target']);
        if ($reverseLink) {
            $reverseLink->load(['source', 'target']);

            return response()->json([
                $this->formatLink($link),
                $this->formatLink($reverseLink),
            ], 201);
        }

        return response()->json($this->formatLink($link), 201);
    }

    #[OA\Delete(
        summary: 'Delete Resource Link',
        description: 'Delete a resource link and optionally remove the injected environment variable.',
        path: '/environments/{environment_uuid}/links/{link_id}',
        operationId: 'delete-resource-link',
        security: [['bearerAuth' => []]],
        tags: ['Resource Links'],
        parameters: [
            new OA\Parameter(name: 'environment_uuid', in: 'path', required: true, description: 'Environment UUID', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'link_id', in: 'path', required: true, description: 'Link ID', schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'remove_env_var', in: 'query', required: false, description: 'Remove the injected env variable', schema: new OA\Schema(type: 'boolean', default: true)),
        ],
        responses: [
            new OA\Response(response: 204, description: 'Link deleted'),
            new OA\Response(response: 401, ref: '#/components/responses/401'),
            new OA\Response(response: 404, description: 'Link not found.'),
        ]
    )]
    public function destroy(Request $request, string $environment_uuid, int $link_id)
    {
        $teamId = getTeamIdFromToken();
        if (is_null($teamId)) {
            return invalidTokenResponse();
        }

        $environment = Environment::whereUuid($environment_uuid)
            ->whereHas('project', fn ($q) => $q->where('team_id', $teamId))
            ->first();

        if (! $environment) {
            return response()->json(['message' => 'Environment not found.'], 404);
        }

        $link = ResourceLink::where('id', $link_id)
            ->where('environment_id', $environment->id)
            ->first();

        if (! $link) {
            return response()->json(['message' => 'Link not found.'], 404);
        }

        // Remove injected env variable if requested (default: true)
        $removeEnvVar = $request->boolean('remove_env_var', true);
        if ($removeEnvVar && $link->source instanceof Application) {
            // Determine the correct env key based on target type
            $envKey = $link->target_type === Application::class
                ? $link->getSmartAppEnvKey()
                : $link->getEnvKey();
            $link->source->environment_variables()
                ->where('key', $envKey)
                ->delete();
        }

        // For app-to-app links, also delete the reverse link (bidirectional)
        if ($link->target_type === Application::class && $link->source_type === Application::class) {
            $reverseLink = ResourceLink::where('source_type', Application::class)
                ->where('source_id', $link->target_id)
                ->where('target_type', Application::class)
                ->where('target_id', $link->source_id)
                ->where('environment_id', $environment->id)
                ->first();

            if ($reverseLink) {
                if ($removeEnvVar && $reverseLink->source instanceof Application) {
                    $reverseEnvKey = $reverseLink->getSmartAppEnvKey();
                    $reverseLink->source->environment_variables()
                        ->where('key', $reverseEnvKey)
                        ->delete();
                }
                $reverseLink->delete();
            }
        }

        $link->delete();

        return response()->noContent();
    }

    #[OA\Patch(
        summary: 'Update Resource Link',
        description: 'Update a resource link settings.',
        path: '/environments/{environment_uuid}/links/{link_id}',
        operationId: 'update-resource-link',
        security: [['bearerAuth' => []]],
        tags: ['Resource Links'],
        parameters: [
            new OA\Parameter(name: 'environment_uuid', in: 'path', required: true, description: 'Environment UUID', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'link_id', in: 'path', required: true, description: 'Link ID', schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'inject_as', type: 'string', nullable: true, description: 'Custom env variable name'),
                    new OA\Property(property: 'auto_inject', type: 'boolean', description: 'Auto-inject on deploy'),
                    new OA\Property(property: 'use_external_url', type: 'boolean', description: 'Use external FQDN instead of internal Docker URL'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Link updated'),
            new OA\Response(response: 401, ref: '#/components/responses/401'),
            new OA\Response(response: 404, description: 'Link not found.'),
        ]
    )]
    public function update(Request $request, string $environment_uuid, int $link_id)
    {
        $teamId = getTeamIdFromToken();
        if (is_null($teamId)) {
            return invalidTokenResponse();
        }

        $environment = Environment::whereUuid($environment_uuid)
            ->whereHas('project', fn ($q) => $q->where('team_id', $teamId))
            ->first();

        if (! $environment) {
            return response()->json(['message' => 'Environment not found.'], 404);
        }

        $link = ResourceLink::where('id', $link_id)
            ->where('environment_id', $environment->id)
            ->first();

        if (! $link) {
            return response()->json(['message' => 'Link not found.'], 404);
        }

        $validated = $request->validate([
            'inject_as' => 'nullable|string|max:255',
            'auto_inject' => 'boolean',
            'use_external_url' => 'boolean',
        ]);

        // Determine old env key before update
        $oldEnvKey = $link->target_type === Application::class
            ? $link->getSmartAppEnvKey()
            : $link->getEnvKey();

        $link->update($validated);

        // Determine new env key after update
        $newEnvKey = $link->target_type === Application::class
            ? $link->getSmartAppEnvKey()
            : $link->getEnvKey();

        if ($oldEnvKey !== $newEnvKey && $link->source instanceof Application) {
            // Remove old env var
            $link->source->environment_variables()
                ->where('key', $oldEnvKey)
                ->delete();

            // Inject new one if auto_inject is enabled
            if ($link->auto_inject) {
                $link->source->autoInjectDatabaseUrl();
            }
        }

        return response()->json($this->formatLink($link->load(['source', 'target'])));
    }

    /**
     * Format link for API response.
     */
    private function formatLink(ResourceLink $link): array
    {
        $targetType = match ($link->target_type) {
            \App\Models\StandalonePostgresql::class => 'postgresql',
            \App\Models\StandaloneMysql::class => 'mysql',
            \App\Models\StandaloneMariadb::class => 'mariadb',
            \App\Models\StandaloneRedis::class => 'redis',
            \App\Models\StandaloneKeydb::class => 'keydb',
            \App\Models\StandaloneDragonfly::class => 'dragonfly',
            \App\Models\StandaloneMongodb::class => 'mongodb',
            \App\Models\StandaloneClickhouse::class => 'clickhouse',
            \App\Models\Application::class => 'application',
            default => 'unknown',
        };

        // Use smart env key for application targets
        $envKey = $link->target_type === Application::class
            ? $link->getSmartAppEnvKey()
            : $link->getEnvKey();

        return [
            'id' => $link->id,
            'environment_id' => $link->environment_id,
            'source_type' => 'application',
            'source_id' => $link->source_id,
            'source_name' => $link->source?->getAttribute('name'),
            'target_type' => $targetType,
            'target_id' => $link->target_id,
            'target_name' => $link->target?->getAttribute('name'),
            'inject_as' => $link->inject_as,
            'env_key' => $envKey,
            'auto_inject' => $link->auto_inject,
            'use_external_url' => $link->use_external_url,
            'created_at' => $link->created_at->toIso8601String(),
            'updated_at' => $link->updated_at->toIso8601String(),
        ];
    }
}
