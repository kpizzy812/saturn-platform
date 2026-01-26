<?php

/**
 * Environment variable helper functions.
 *
 * Contains functions for parsing, manipulating, and working with
 * environment variables in various formats.
 */

use App\Models\Application;
use App\Models\GithubApp;
use App\Models\Server;
use App\Models\Service;
use App\Models\StandaloneClickhouse;
use App\Models\StandaloneDragonfly;
use App\Models\StandaloneKeydb;
use App\Models\StandaloneMariadb;
use App\Models\StandaloneMongodb;
use App\Models\StandaloneMysql;
use App\Models\StandalonePostgresql;
use App\Models\StandaloneRedis;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Support\Stringable;
use Visus\Cuid2\Cuid2;

/**
 * Parse ENV file format string to array.
 */
function parseEnvFormatToArray($env_file_contents)
{
    $env_array = [];
    $lines = explode("\n", $env_file_contents);
    foreach ($lines as $line) {
        if ($line === '' || substr($line, 0, 1) === '#') {
            continue;
        }
        $equals_pos = strpos($line, '=');
        if ($equals_pos !== false) {
            $key = substr($line, 0, $equals_pos);
            $value = substr($line, $equals_pos + 1);
            if (substr($value, 0, 1) === '"' && substr($value, -1) === '"') {
                $value = substr($value, 1, -1);
            } elseif (substr($value, 0, 1) === "'" && substr($value, -1) === "'") {
                $value = substr($value, 1, -1);
            }
            $env_array[$key] = $value;
        }
    }

    return $env_array;
}

/**
 * Parse command from magic environment variable key.
 */
function parseCommandFromMagicEnvVariable(Str|string $key): Stringable
{
    $value = str($key);
    $count = substr_count($value->value(), '_');
    $command = null;
    if ($count === 2) {
        if ($value->startsWith('SERVICE_FQDN') || $value->startsWith('SERVICE_URL')) {
            // SERVICE_FQDN_UMAMI
            $command = $value->after('SERVICE_')->beforeLast('_');
        } else {
            // SERVICE_BASE64_UMAMI
            $command = $value->after('SERVICE_')->beforeLast('_');
        }
    }
    if ($count === 3) {
        if ($value->startsWith('SERVICE_FQDN') || $value->startsWith('SERVICE_URL')) {
            // SERVICE_FQDN_UMAMI_1000
            $command = $value->after('SERVICE_')->before('_');
        } else {
            // SERVICE_BASE64_64_UMAMI
            $command = $value->after('SERVICE_')->beforeLast('_');
        }
    }

    return str($command);
}

/**
 * Parse environment variable string to extract command, service, value, and port.
 */
function parseEnvVariable(Str|string $value)
{
    $value = str($value);
    $count = substr_count($value->value(), '_');
    $command = null;
    $forService = null;
    $generatedValue = null;
    $port = null;
    if ($value->startsWith('SERVICE')) {
        if ($count === 2) {
            if ($value->startsWith('SERVICE_FQDN') || $value->startsWith('SERVICE_URL')) {
                // SERVICE_FQDN_UMAMI
                $command = $value->after('SERVICE_')->beforeLast('_');
                $forService = $value->afterLast('_');
            } else {
                // SERVICE_BASE64_UMAMI
                $command = $value->after('SERVICE_')->beforeLast('_');
            }
        }
        if ($count === 3) {
            if ($value->startsWith('SERVICE_FQDN') || $value->startsWith('SERVICE_URL')) {
                // SERVICE_FQDN_UMAMI_1000
                $command = $value->after('SERVICE_')->before('_');
                $forService = $value->after('SERVICE_')->after('_')->before('_');
                $port = $value->afterLast('_');
                if (filter_var($port, FILTER_VALIDATE_INT) === false) {
                    $port = null;
                }
            } else {
                // SERVICE_BASE64_64_UMAMI
                $command = $value->after('SERVICE_')->beforeLast('_');
            }
        }
    }

    return [
        'command' => $command,
        'forService' => $forService,
        'generatedValue' => $generatedValue,
        'port' => $port,
    ];
}

/**
 * Add Saturn default environment variables to a resource.
 * - SATURN_APP_NAME
 * - SATURN_PROJECT_NAME
 * - SATURN_SERVER_IP
 * - SATURN_ENVIRONMENT_NAME
 *
 * These variables are added in place to the $where_to_add array.
 */
function add_saturn_default_environment_variables(StandaloneRedis|StandalonePostgresql|StandaloneMongodb|StandaloneMysql|StandaloneMariadb|StandaloneKeydb|StandaloneDragonfly|StandaloneClickhouse|Application|Service $resource, Collection &$where_to_add, ?Collection $where_to_check = null)
{
    // Currently disabled
    return;
    if ($resource instanceof Service) {
        $ip = $resource->server->ip;
    } else {
        $ip = $resource->destination->server->ip;
    }
    if (isAssociativeArray($where_to_add)) {
        $isAssociativeArray = true;
    } else {
        $isAssociativeArray = false;
    }
    if ($where_to_check != null && $where_to_check->where('key', 'SATURN_APP_NAME')->isEmpty()) {
        if ($isAssociativeArray) {
            $where_to_add->put('SATURN_APP_NAME', "\"{$resource->name}\"");
        } else {
            $where_to_add->push("SATURN_APP_NAME=\"{$resource->name}\"");
        }
    }
    if ($where_to_check != null && $where_to_check->where('key', 'SATURN_SERVER_IP')->isEmpty()) {
        if ($isAssociativeArray) {
            $where_to_add->put('SATURN_SERVER_IP', "\"{$ip}\"");
        } else {
            $where_to_add->push("SATURN_SERVER_IP=\"{$ip}\"");
        }
    }
    if ($where_to_check != null && $where_to_check->where('key', 'SATURN_ENVIRONMENT_NAME')->isEmpty()) {
        if ($isAssociativeArray) {
            $where_to_add->put('SATURN_ENVIRONMENT_NAME', "\"{$resource->environment->name}\"");
        } else {
            $where_to_add->push("SATURN_ENVIRONMENT_NAME=\"{$resource->environment->name}\"");
        }
    }
    if ($where_to_check != null && $where_to_check->where('key', 'SATURN_PROJECT_NAME')->isEmpty()) {
        if ($isAssociativeArray) {
            $where_to_add->put('SATURN_PROJECT_NAME', "\"{$resource->project()->name}\"");
        } else {
            $where_to_add->push("SATURN_PROJECT_NAME=\"{$resource->project()->name}\"");
        }
    }
}

/**
 * Load configuration from a Git repository.
 */
function loadConfigFromGit(string $repository, string $branch, string $base_directory, int $server_id, int $team_id)
{
    $server = Server::find($server_id)->where('team_id', $team_id)->first();
    if (! $server) {
        return;
    }
    $uuid = new Cuid2;
    $cloneCommand = "git clone --no-checkout -b $branch $repository .";
    $workdir = rtrim($base_directory, '/');
    $fileList = collect([".$workdir/saturn.json"]);
    $commands = collect([
        "rm -rf /tmp/{$uuid}",
        "mkdir -p /tmp/{$uuid}",
        "cd /tmp/{$uuid}",
        $cloneCommand,
        'git sparse-checkout init --cone',
        "git sparse-checkout set {$fileList->implode(' ')}",
        'git read-tree -mu HEAD',
        "cat .$workdir/saturn.json",
        'rm -rf /tmp/{$uuid}',
    ]);
    try {
        return instant_remote_process($commands, $server);
    } catch (\Exception) {
        // continue
    }
}

/**
 * Convert Git URL to the appropriate format based on deployment type.
 */
function convertGitUrl(string $gitRepository, string $deploymentType, ?GithubApp $source = null): array
{
    $git_repository = $gitRepository;
    $git_port = 22;
    $git_base_url = 'github.com';

    if ($deploymentType === 'deploy_key' || $deploymentType === 'dockerfile') {
        // If the git repository starts with http, it's an HTTP-based repository
        if (str($gitRepository)->startsWith('http://') || str($gitRepository)->startsWith('https://')) {
            $url = parse_url($gitRepository);
            $git_base_url = $url['host'];
            if (isset($url['port'])) {
                $git_port = $url['port'];
            }
            if (isset($url['path'])) {
                $git_repository = ltrim($url['path'], '/');
            }
            if (str($git_repository)->endsWith('.git')) {
                $git_repository = str($git_repository)->beforeLast('.git')->value();
            }
        } elseif (str($gitRepository)->contains(':')) {
            // It's an SSH-based repository
            $git_base_url = str($gitRepository)->before(':')->value();
            if (str($git_base_url)->contains('@')) {
                $git_base_url = str($git_base_url)->after('@')->value();
            }
            $git_repository = str($gitRepository)->after(':')->value();
            if (str($git_repository)->endsWith('.git')) {
                $git_repository = str($git_repository)->beforeLast('.git')->value();
            }
        }
    }

    if ($source) {
        $git_base_url = $source->html_url;
        $parsed_url = parse_url($git_base_url);
        if (isset($parsed_url['host'])) {
            $git_base_url = $parsed_url['host'];
        }
        if (isset($parsed_url['port'])) {
            $git_port = $parsed_url['port'];
        }
    }

    return [
        'git_repository' => $git_repository,
        'git_port' => $git_port,
        'git_base_url' => $git_base_url,
    ];
}

/**
 * Get the latest Sentinel version from Saturn platform.
 */
function get_latest_sentinel_version(): string
{
    try {
        $response = \Illuminate\Support\Facades\Http::get(config('constants.saturn.versions_url'));
        $versions = $response->json();

        return data_get($versions, 'coolify.sentinel.version');
    } catch (\Throwable) {
        return '0.0.0';
    }
}

/**
 * Get the latest version of Saturn.
 */
function get_latest_version_of_saturn(): string
{
    try {
        $versions = get_versions_data();

        return data_get($versions, 'coolify.v4.version', '0.0.0');
    } catch (\Throwable $e) {

        return '0.0.0';
    }
}

/**
 * Check if email rate limiting is enabled.
 */
function isEmailRateLimited(string $limiterKey, int $decaySeconds = 3600, ?callable $callbackOnSuccess = null): bool
{
    $key = 'email_limiter_'.$limiterKey;
    $attempts = \Illuminate\Support\Facades\RateLimiter::attempt(
        key: $key,
        maxAttempts: 1,
        callback: function () use ($callbackOnSuccess) {
            if ($callbackOnSuccess) {
                $callbackOnSuccess();
            }

            return true;
        },
        decaySeconds: $decaySeconds
    );

    return ! $attempts;
}

/**
 * Get default Nginx configuration.
 */
function defaultNginxConfiguration(string $type = 'static'): string
{
    if ($type === 'static') {
        return <<<'NGINX'
server {
    listen 80;
    listen [::]:80;
    server_name localhost;

    root /usr/share/nginx/html;
    index index.html index.htm;

    location / {
        try_files $uri $uri/ /index.html;
    }

    location ~* \.(js|css|png|jpg|jpeg|gif|ico|svg|woff|woff2|ttf|eot)$ {
        expires 1y;
        add_header Cache-Control "public, immutable";
    }

    gzip on;
    gzip_vary on;
    gzip_proxied any;
    gzip_comp_level 6;
    gzip_types text/plain text/css text/xml application/json application/javascript application/rss+xml application/atom+xml image/svg+xml;
}
NGINX;
    }

    return '';
}
