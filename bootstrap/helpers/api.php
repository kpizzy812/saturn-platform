<?php

use App\Enums\BuildPackTypes;
use App\Enums\RedirectTypes;
use App\Enums\StaticImageTypes;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

function getTeamIdFromToken()
{
    $user = auth()->user();
    if (! $user) {
        return null;
    }

    // API token auth: get team_id from token
    $token = $user->currentAccessToken();
    if ($token && data_get($token, 'team_id')) {
        return data_get($token, 'team_id');
    }

    // Session auth fallback (SPA): use current team
    $currentTeam = $user->currentTeam();
    if ($currentTeam) {
        return $currentTeam->id;
    }

    return null;
}
function invalidTokenResponse()
{
    return response()->json(['message' => 'Invalid token.', 'docs' => '#/api-reference/authorization'], 400);
}

function serializeApiResponse($data)
{
    if ($data instanceof Collection) {
        return $data->map(function ($d) {
            $d = collect($d)->sortKeys();
            $created_at = data_get($d, 'created_at');
            $updated_at = data_get($d, 'updated_at');
            if ($created_at) {
                unset($d['created_at']);
                $d['created_at'] = $created_at;
            }
            if ($updated_at) {
                unset($d['updated_at']);
                $d['updated_at'] = $updated_at;
            }
            if (data_get($d, 'name')) {
                $d = $d->prepend($d['name'], 'name');
            }
            if (data_get($d, 'description')) {
                $d = $d->prepend($d['description'], 'description');
            }
            if (data_get($d, 'uuid')) {
                $d = $d->prepend($d['uuid'], 'uuid');
            }

            if (! is_null(data_get($d, 'id'))) {
                $d = $d->prepend($d['id'], 'id');
            }

            return $d;
        });
    } else {
        $d = collect($data)->sortKeys();
        $created_at = data_get($d, 'created_at');
        $updated_at = data_get($d, 'updated_at');
        if ($created_at) {
            unset($d['created_at']);
            $d['created_at'] = $created_at;
        }
        if ($updated_at) {
            unset($d['updated_at']);
            $d['updated_at'] = $updated_at;
        }
        if (data_get($d, 'name')) {
            $d = $d->prepend($d['name'], 'name');
        }
        if (data_get($d, 'description')) {
            $d = $d->prepend($d['description'], 'description');
        }
        if (data_get($d, 'uuid')) {
            $d = $d->prepend($d['uuid'], 'uuid');
        }

        if (! is_null(data_get($d, 'id'))) {
            $d = $d->prepend($d['id'], 'id');
        }

        return $d;
    }
}

function sharedDataApplications()
{
    return [
        'git_repository' => 'string',
        'git_branch' => 'string',
        'build_pack' => Rule::enum(BuildPackTypes::class),
        'is_static' => 'boolean',
        'static_image' => Rule::enum(StaticImageTypes::class),
        'domains' => 'string',
        'redirect' => Rule::enum(RedirectTypes::class),
        'git_commit_sha' => ['string', 'regex:/^([a-fA-F0-9]{4,40}|HEAD)$/'],
        'docker_registry_image_name' => 'string|nullable',
        'docker_registry_image_tag' => 'string|nullable',
        'install_command' => 'string|nullable',
        'build_command' => 'string|nullable',
        'start_command' => 'string|nullable',
        'application_type' => 'string|in:web,worker,both',
        'ports_exposes' => ['string', 'nullable', 'regex:/^(\d+)(,\d+)*$/', function ($attribute, $value, $fail) {
            if ($value === null) {
                return;
            }
            foreach (explode(',', $value) as $port) {
                $port = (int) $port;
                if ($port < 1 || $port > 65535) {
                    $fail("Each port in $attribute must be between 1 and 65535.");
                }
            }
        }],
        'ports_mappings' => ['string', 'regex:/^(\d+:\d+)(,\d+:\d+)*$/', 'nullable', function ($attribute, $value, $fail) {
            if ($value === null) {
                return;
            }
            foreach (explode(',', $value) as $mapping) {
                foreach (explode(':', $mapping) as $port) {
                    $port = (int) $port;
                    if ($port < 1 || $port > 65535) {
                        $fail("Each port in $attribute must be between 1 and 65535.");
                    }
                }
            }
        }],
        'custom_network_aliases' => 'string|nullable',
        'base_directory' => 'string|nullable',
        'publish_directory' => 'string|nullable',
        'health_check_enabled' => 'boolean',
        'health_check_path' => 'string|max:2048',
        'health_check_port' => 'integer|nullable|min:1|max:65535',
        'health_check_host' => 'string|max:255',
        'health_check_method' => 'string|in:GET,POST,HEAD,OPTIONS',
        'health_check_return_code' => 'integer|min:100|max:599',
        'health_check_scheme' => 'string|in:http,https',
        'health_check_response_text' => 'string|nullable|max:1024',
        'health_check_interval' => 'integer|min:1|max:3600',
        'health_check_timeout' => 'integer|min:1|max:300',
        'health_check_retries' => 'integer|min:0|max:100',
        'health_check_start_period' => 'integer|min:0|max:3600',
        'limits_memory' => ['string', 'regex:/^(\d+(\.\d+)?)(b|k|m|g)$/i'],
        'limits_memory_swap' => ['string', 'regex:/^(\d+(\.\d+)?)(b|k|m|g)$/i'],
        'limits_memory_swappiness' => 'integer|min:0|max:100',
        'limits_memory_reservation' => ['string', 'regex:/^(\d+(\.\d+)?)(b|k|m|g)$/i'],
        'limits_cpus' => ['string', 'regex:/^\d+(\.\d+)?$/'],
        'limits_cpuset' => ['string', 'nullable', 'regex:/^(\d+(-\d+)?)(,\d+(-\d+)?)*$/'],
        'limits_cpu_shares' => 'integer|min:2|max:262144',
        'custom_labels' => 'string|nullable',
        'custom_docker_run_options' => 'string|nullable',
        'post_deployment_command' => 'string|nullable',
        'post_deployment_command_container' => 'string',
        'pre_deployment_command' => 'string|nullable',
        'pre_deployment_command_container' => 'string',
        'manual_webhook_secret_github' => 'string|nullable',
        'manual_webhook_secret_gitlab' => 'string|nullable',
        'manual_webhook_secret_bitbucket' => 'string|nullable',
        'manual_webhook_secret_gitea' => 'string|nullable',
        'docker_compose_location' => 'string',
        'docker_compose' => 'string|nullable',
        'docker_compose_raw' => 'string|nullable',
        'docker_compose_domains' => 'array|nullable',
        'docker_compose_custom_start_command' => 'string|nullable',
        'docker_compose_custom_build_command' => 'string|nullable',
    ];
}

function validateIncomingRequest(Request $request)
{
    // check if request is json
    if (! $request->isJson()) {
        return response()->json([
            'message' => 'Invalid request.',
            'error' => 'Content-Type must be application/json.',
        ], 400);
    }
    // check if request is valid json
    if (! json_decode($request->getContent())) {
        return response()->json([
            'message' => 'Invalid request.',
            'error' => 'Invalid JSON.',
        ], 400);
    }
    // check if valid json is empty
    if (empty($request->json()->all())) {
        return response()->json([
            'message' => 'Invalid request.',
            'error' => 'Empty JSON.',
        ], 400);
    }
}

function removeUnnecessaryFieldsFromRequest(Request $request)
{
    $request->offsetUnset('project_uuid');
    $request->offsetUnset('environment_name');
    $request->offsetUnset('environment_uuid');
    $request->offsetUnset('destination_uuid');
    $request->offsetUnset('server_uuid');
    $request->offsetUnset('type');
    $request->offsetUnset('domains');
    $request->offsetUnset('instant_deploy');
    $request->offsetUnset('github_app_uuid');
    $request->offsetUnset('private_key_uuid');
    $request->offsetUnset('use_build_server');
    $request->offsetUnset('is_static');
    $request->offsetUnset('force_domain_override');
    $request->offsetUnset('autogenerate_domain');
}
