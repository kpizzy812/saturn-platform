<?php

use App\Exceptions\RateLimitException;
use App\Models\GithubApp;
use App\Models\GitlabApp;
use App\Services\CircuitBreaker;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Lcobucci\JWT\Encoding\ChainedFormatter;
use Lcobucci\JWT\Encoding\JoseEncoder;
use Lcobucci\JWT\Signer\Key\InMemory;
use Lcobucci\JWT\Signer\Rsa\Sha256;
use Lcobucci\JWT\Token\Builder;

/**
 * Validate that a GitHub App api_url is safe to use (not pointing to internal services).
 *
 * @throws \RuntimeException If the URL is unsafe
 */
function validateGithubApiUrl(string $apiUrl): void
{
    $parsed = parse_url($apiUrl);
    if (! $parsed || ! isset($parsed['scheme'], $parsed['host'])) {
        throw new \RuntimeException('Invalid GitHub API URL format.');
    }
    if ($parsed['scheme'] !== 'https') {
        throw new \RuntimeException('GitHub API URL must use HTTPS.');
    }
    $host = $parsed['host'];

    // Allow known GitHub domains
    if ($host === 'api.github.com' || str_ends_with($host, '.github.com') || str_ends_with($host, '.ghe.com')) {
        return;
    }

    // For self-hosted GitHub Enterprise: resolve and block private/reserved IPs
    $ip = gethostbyname($host);
    if ($ip === $host) {
        throw new \RuntimeException("Cannot resolve GitHub API host: {$host}");
    }
    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
        throw new \RuntimeException("GitHub API URL resolves to a private/reserved IP ({$ip}), which is blocked.");
    }
}

function generateGithubToken(GithubApp $source, string $type)
{
    // Validate api_url on every call to prevent SSRF via post-creation URL modification
    validateGithubApiUrl($source->api_url);

    $response = Http::get("{$source->api_url}/zen");
    $serverTime = CarbonImmutable::now()->setTimezone('UTC');
    $githubTime = Carbon::parse($response->header('date'));
    $timeDiff = abs($serverTime->diffInSeconds($githubTime));

    if ($timeDiff > 50) {
        throw new \Exception(
            'System time is out of sync with GitHub API time:<br>'.
            '- System time: '.$serverTime->format('Y-m-d H:i:s').' UTC<br>'.
            '- GitHub time: '.$githubTime->format('Y-m-d H:i:s').' UTC<br>'.
            '- Difference: '.$timeDiff.' seconds<br>'.
            'Please synchronize your system clock.'
        );
    }

    /** @phpstan-ignore property.notFound */
    $signingKey = InMemory::plainText($source->privateKey->private_key);
    $algorithm = new Sha256;
    $tokenBuilder = (new Builder(new JoseEncoder, ChainedFormatter::default()));
    $now = CarbonImmutable::now()->setTimezone('UTC');
    $now = $now->setTime((int) $now->format('H'), (int) $now->format('i'), (int) $now->format('s'));

    $jwt = $tokenBuilder
        ->issuedBy((string) $source->app_id)
        ->issuedAt($now->modify('-1 minute'))
        ->expiresAt($now->modify('+8 minutes'))
        ->getToken($algorithm, $signingKey)
        ->toString();

    return match ($type) {
        'jwt' => $jwt,
        'installation' => (function () use ($source, $jwt) {
            $response = Http::withHeaders([
                'Authorization' => "Bearer $jwt",
                'Accept' => 'application/vnd.github.machine-man-preview+json',
            ])->post("{$source->api_url}/app/installations/{$source->installation_id}/access_tokens");

            if (! $response->successful()) {
                $error = data_get($response->json(), 'message', 'no error message found');
                if ($error === 'Not Found') {
                    $error = 'Repository not found. Is it moved or deleted?';
                }
                throw new RuntimeException("Failed to get installation token for {$source->name} with error: ".$error);
            }

            return $response->json()['token'];
        })(),
        default => throw new \InvalidArgumentException("Unsupported token type: {$type}")
    };
}

function generateGithubInstallationToken(GithubApp $source)
{
    return generateGithubToken($source, 'installation');
}

function generateGithubJwt(GithubApp $source)
{
    return generateGithubToken($source, 'jwt');
}

function githubApi(GithubApp|GitlabApp|null $source, string $endpoint, string $method = 'get', ?array $data = null, bool $throwError = true)
{
    if (is_null($source)) {
        throw new \Exception('Source is required for API calls');
    }

    if ($source->getMorphClass() !== GithubApp::class) {
        throw new \InvalidArgumentException("Unsupported source type: {$source->getMorphClass()}");
    }

    if (CircuitBreaker::isOpen('github')) {
        throw new \Exception('GitHub API circuit breaker is open — too many recent failures. Please retry later.');
    }

    // Validate api_url on every call to prevent SSRF
    validateGithubApiUrl($source->api_url);

    if ($source->is_public) {
        $response = Http::GitHub($source->api_url)->$method($endpoint);
    } else {
        $token = generateGithubInstallationToken($source);
        if ($data && in_array(strtolower($method), ['post', 'patch', 'put'])) {
            $response = Http::GitHub($source->api_url, $token)->$method($endpoint, $data);
        } else {
            $response = Http::GitHub($source->api_url, $token)->$method($endpoint);
        }
    }

    if (! $response->successful()) {
        // Throw RateLimitException on 429 so callers can handle backoff properly
        if ($response->status() === 429) {
            $retryAfter = $response->header('Retry-After');
            if ($retryAfter === null) {
                $resetTime = $response->header('X-RateLimit-Reset');
                $retryAfter = $resetTime ? max(0, (int) $resetTime - time()) : null;
            }

            CircuitBreaker::recordFailure('github');

            throw new RateLimitException(
                'GitHub API rate limit exceeded. Please try again later.',
                $retryAfter !== null ? (int) $retryAfter : null
            );
        }

        CircuitBreaker::recordFailure('github');

        if ($throwError) {
            $resetTime = Carbon::parse((int) $response->header('X-RateLimit-Reset'))->format('Y-m-d H:i:s');
            $errorMessage = data_get($response->json(), 'message', 'no error message found');
            $remainingCalls = $response->header('X-RateLimit-Remaining', '0');

            throw new \Exception(
                'GitHub API call failed:<br>'.
                "Error: {$errorMessage}<br>".
                'Rate Limit Status:<br>'.
                "- Remaining Calls: {$remainingCalls}<br>".
                "- Reset Time: {$resetTime} UTC"
            );
        }
    } else {
        CircuitBreaker::recordSuccess('github');
    }

    return [
        'rate_limit_remaining' => $response->header('X-RateLimit-Remaining'),
        'rate_limit_reset' => $response->header('X-RateLimit-Reset'),
        'data' => collect($response->json()),
    ];
}

function isValidGithubUrl(string $url): bool
{
    $parsed = parse_url($url);

    return $parsed !== false
        && isset($parsed['scheme'], $parsed['host'])
        && $parsed['scheme'] === 'https'
        && (str_ends_with($parsed['host'], 'github.com') || str_ends_with($parsed['host'], '.ghe.com'));
}

function getInstallationPath(GithubApp $source)
{
    $github = GithubApp::where('uuid', $source->uuid)->first();

    if (! isValidGithubUrl($github->html_url)) {
        throw new \RuntimeException('Invalid GitHub App html_url: must be a valid GitHub domain.');
    }

    $name = str(Str::kebab($github->name));
    $installation_path = $github->html_url === 'https://github.com' ? 'apps' : 'github-apps';

    return "$github->html_url/$installation_path/$name/installations/new";
}

function getPermissionsPath(GithubApp $source)
{
    $github = GithubApp::where('uuid', $source->uuid)->first();

    if (! isValidGithubUrl($github->html_url)) {
        throw new \RuntimeException('Invalid GitHub App html_url: must be a valid GitHub domain.');
    }

    $name = str(Str::kebab($github->name));

    return "$github->html_url/settings/apps/$name/permissions";
}

function loadRepositoryByPage(GithubApp $source, string $token, int $page)
{
    $response = Http::GitHub($source->api_url, $token)
        ->timeout(20)
        ->retry(3, 200, throw: false)
        ->get('/installation/repositories', [
            'per_page' => 100,
            'page' => $page,
        ]);
    $json = $response->json();
    if ($response->status() !== 200) {
        return [
            'total_count' => 0,
            'repositories' => [],
        ];
    }

    if ($json['total_count'] === 0) {
        return [
            'total_count' => 0,
            'repositories' => [],
        ];
    }

    return [
        'total_count' => $json['total_count'],
        'repositories' => $json['repositories'],
    ];
}
