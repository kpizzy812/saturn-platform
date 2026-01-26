<?php

/**
 * Generator helper functions.
 *
 * Contains functions for generating random names, SSH keys,
 * passwords, and various identifiers.
 */

use App\Models\Application;
use App\Models\Service;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Lcobucci\JWT\Encoding\ChainedFormatter;
use Lcobucci\JWT\Encoding\JoseEncoder;
use Lcobucci\JWT\Signer\Hmac\Sha256;
use Lcobucci\JWT\Signer\Key\InMemory;
use Lcobucci\JWT\Token\Builder;
use phpseclib3\Crypt\EC;
use phpseclib3\Crypt\RSA;
use Visus\Cuid2\Cuid2;

/**
 * Generate a README file content with resource name and date.
 */
function generate_readme_file(string $name, string $updated_at): string
{
    $name = sanitize_string($name);
    $updated_at = sanitize_string($updated_at);

    return "Resource name: $name\nLatest Deployment Date: $updated_at";
}

/**
 * Generate a random name using alliteration.
 */
function generate_random_name(?string $cuid = null): string
{
    $generator = new \Nubs\RandomNameGenerator\All(
        [
            new \Nubs\RandomNameGenerator\Alliteration,
        ]
    );
    if (is_null($cuid)) {
        $cuid = new Cuid2;
    }

    return Str::kebab("{$generator->getName()}-$cuid");
}

/**
 * Generate an SSH key pair.
 *
 * @param  string  $type  Key type: 'rsa' or 'ed25519'
 * @return array{private: string, public: string}
 *
 * @throws Exception If invalid key type is provided
 */
function generateSSHKey(string $type = 'rsa')
{
    if ($type === 'rsa') {
        $key = RSA::createKey();

        return [
            'private' => $key->toString('PKCS1'),
            'public' => $key->getPublicKey()->toString('OpenSSH', ['comment' => 'saturn-generated-ssh-key']),
        ];
    } elseif ($type === 'ed25519') {
        $key = EC::createKey('Ed25519');

        return [
            'private' => $key->toString('OpenSSH'),
            'public' => $key->getPublicKey()->toString('OpenSSH', ['comment' => 'saturn-generated-ssh-key']),
        ];
    }
    throw new Exception('Invalid key type');
}

/**
 * Format a private key to ensure proper line ending.
 */
function formatPrivateKey(string $privateKey)
{
    $privateKey = trim($privateKey);
    if (! str_ends_with($privateKey, "\n")) {
        $privateKey .= "\n";
    }

    return $privateKey;
}

/**
 * Generate an application name from git repository and branch.
 */
function generate_application_name(string $git_repository, string $git_branch, ?string $cuid = null): string
{
    if (is_null($cuid)) {
        $cuid = new Cuid2;
    }

    return Str::kebab("$git_repository:$git_branch-$cuid");
}

/**
 * Sort branches by priority: main first, master second, then alphabetically.
 *
 * @param  Collection  $branches  Collection of branch objects with 'name' key
 */
function sortBranchesByPriority(Collection $branches): Collection
{
    return $branches->sortBy(function ($branch) {
        $name = data_get($branch, 'name');

        return match ($name) {
            'main' => '0_main',
            'master' => '1_master',
            default => '2_'.$name,
        };
    })->values();
}

/**
 * Generate a value based on the command type.
 *
 * @param  string  $command  The command/type to generate
 * @param  Service|Application|null  $service  Optional service for context-specific generation
 */
function generateEnvValue(string $command, Service|Application|null $service = null)
{
    switch ($command) {
        case 'PASSWORD':
            $generatedValue = Str::password(symbols: false);
            break;
        case 'PASSWORD_64':
            $generatedValue = Str::password(length: 64, symbols: false);
            break;
        case 'PASSWORDWITHSYMBOLS':
            $generatedValue = Str::password(symbols: true);
            break;
        case 'PASSWORDWITHSYMBOLS_64':
            $generatedValue = Str::password(length: 64, symbols: true);
            break;
            // This is not base64, it's just a random string
        case 'BASE64_64':
            $generatedValue = Str::random(64);
            break;
        case 'BASE64_128':
            $generatedValue = Str::random(128);
            break;
        case 'BASE64':
        case 'BASE64_32':
            $generatedValue = Str::random(32);
            break;
            // This is base64,
        case 'REALBASE64_64':
            $generatedValue = base64_encode(Str::random(64));
            break;
        case 'REALBASE64_128':
            $generatedValue = base64_encode(Str::random(128));
            break;
        case 'REALBASE64':
        case 'REALBASE64_32':
            $generatedValue = base64_encode(Str::random(32));
            break;
        case 'HEX_32':
            $generatedValue = bin2hex(Str::random(32));
            break;
        case 'HEX_64':
            $generatedValue = bin2hex(Str::random(64));
            break;
        case 'HEX_128':
            $generatedValue = bin2hex(Str::random(128));
            break;
        case 'USER':
            $generatedValue = Str::random(16);
            break;
        case 'SUPABASEANON':
            $signingKey = $service->environment_variables()->where('key', 'SERVICE_PASSWORD_JWT')->first();
            if (is_null($signingKey)) {
                return;
            } else {
                $signingKey = $signingKey->value;
            }
            $key = InMemory::plainText($signingKey);
            $algorithm = new Sha256;
            $tokenBuilder = (new Builder(new JoseEncoder, ChainedFormatter::default()));
            $now = CarbonImmutable::now();
            $now = $now->setTime($now->format('H'), $now->format('i'));
            $token = $tokenBuilder
                ->issuedBy('supabase')
                ->issuedAt($now)
                ->expiresAt($now->modify('+100 year'))
                ->withClaim('role', 'anon')
                ->getToken($algorithm, $key);
            $generatedValue = $token->toString();
            break;
        case 'SUPABASESERVICE':
            $signingKey = $service->environment_variables()->where('key', 'SERVICE_PASSWORD_JWT')->first();
            if (is_null($signingKey)) {
                return;
            } else {
                $signingKey = $signingKey->value;
            }
            $key = InMemory::plainText($signingKey);
            $algorithm = new Sha256;
            $tokenBuilder = (new Builder(new JoseEncoder, ChainedFormatter::default()));
            $now = CarbonImmutable::now();
            $now = $now->setTime($now->format('H'), $now->format('i'));
            $token = $tokenBuilder
                ->issuedBy('supabase')
                ->issuedAt($now)
                ->expiresAt($now->modify('+100 year'))
                ->withClaim('role', 'service_role')
                ->getToken($algorithm, $key);
            $generatedValue = $token->toString();
            break;
        default:
            // $generatedValue = Str::random(16);
            $generatedValue = null;
            break;
    }

    return $generatedValue;
}

/**
 * Generate Docker Compose service name with optional pull request suffix.
 */
function generateDockerComposeServiceName(mixed $services, int $pullRequestId = 0): Collection
{
    return collect($services)->map(function ($service, $key) use ($pullRequestId) {
        return addPreviewDeploymentSuffix($key, $pullRequestId);
    });
}

/**
 * Add preview deployment suffix to a name.
 */
function addPreviewDeploymentSuffix(string $name, int $pull_request_id = 0): string
{
    if ($pull_request_id > 0) {
        return "{$name}-pr-{$pull_request_id}";
    }

    return $name;
}
