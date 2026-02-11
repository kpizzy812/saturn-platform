<?php

/**
 * Network helper functions.
 *
 * Contains functions for URL manipulation, IP address handling,
 * DNS validation, and network-related operations.
 */

use App\Models\InstanceSettings;
use App\Models\Server;
use Illuminate\Process\Pool;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Request;
use PurplePixie\PhpDns\DNSQuery;
use Spatie\Url\Url;

/**
 * Get the base IP address for the instance.
 */
function base_ip(): string
{
    if (isDev()) {
        return 'localhost';
    }
    $settings = instanceSettings();
    if ($settings->public_ipv4) {
        return "$settings->public_ipv4";
    }
    if ($settings->public_ipv6) {
        return "$settings->public_ipv6";
    }

    return 'localhost';
}

/**
 * Get FQDN without the port number.
 */
function getFqdnWithoutPort(string $fqdn)
{
    try {
        $url = Url::fromString($fqdn);
        $host = $url->getHost();
        $scheme = $url->getScheme();
        $path = $url->getPath();

        return "$scheme://$host$path";
    } catch (\Throwable) {
        return $fqdn;
    }
}

/**
 * Get the base URL for the instance.
 * If fqdn is set, return it, otherwise return public ip.
 */
function base_url(bool $withPort = true): string
{
    $settings = instanceSettings();
    if ($settings->fqdn) {
        return $settings->fqdn;
    }
    $port = config('app.port');
    if ($settings->public_ipv4) {
        if ($withPort) {
            if (isDev()) {
                return "http://localhost:$port";
            }

            return "http://$settings->public_ipv4:$port";
        }
        if (isDev()) {
            return 'http://localhost';
        }

        return "http://$settings->public_ipv4";
    }
    if ($settings->public_ipv6) {
        if ($withPort) {
            return "http://$settings->public_ipv6:$port";
        }

        return "http://$settings->public_ipv6";
    }

    return url('/');
}

/**
 * Resolve the wildcard domain for URL generation.
 *
 * Priority: master server wildcard -> target server wildcard -> sslip(master) -> sslip(target)
 */
function resolveWildcardDomain(Server $server): string
{
    // First try master server's wildcard domain (all apps use *.saturn.io)
    $masterServer = Server::masterServer();
    if ($masterServer && $masterServer->id !== $server->id) {
        $masterWildcard = data_get($masterServer, 'settings.wildcard_domain');
        if (! empty($masterWildcard)) {
            return $masterWildcard;
        }
    }

    // Fallback to target server's wildcard domain
    $wildcard = data_get($server, 'settings.wildcard_domain');
    if (! empty($wildcard)) {
        return $wildcard;
    }

    // Last resort: sslip.io for master or target
    if ($masterServer) {
        return sslip($masterServer);
    }

    return sslip($server);
}

/**
 * Generate a DNS-safe subdomain slug from an application name.
 *
 * When $projectName is provided, generates "{project}-{shortId}" format
 * (e.g. "pix11-a1b2c3") for better uniqueness across projects.
 *
 * Without $projectName, falls back to Str::slug($name) for backwards compatibility
 * (e.g. "PixelPets" → "pixelpets", "My Cool App" → "my-cool-app").
 *
 * Ensures uniqueness by appending -2, -3, etc. if the FQDN is already taken.
 */
function generateSubdomainFromName(string $name, Server $server, ?string $projectName = null): string
{
    if ($projectName) {
        $projectSlug = \Illuminate\Support\Str::slug($projectName);
        $shortId = strtolower(\Illuminate\Support\Str::random(6));
        $slug = $projectSlug ? "{$projectSlug}-{$shortId}" : $shortId;
    } else {
        $slug = \Illuminate\Support\Str::slug($name);
    }

    // Fallback if name results in empty slug (e.g. only special characters)
    if (empty($slug)) {
        return strtolower(\Illuminate\Support\Str::random(8));
    }

    // Truncate to 50 chars to leave room for uniqueness suffix (DNS label max = 63)
    $slug = \Illuminate\Support\Str::limit($slug, 50, '');

    // Check uniqueness against existing application FQDNs
    $wildcard = resolveWildcardDomain($server);
    $url = Url::fromString($wildcard);
    $host = $url->getHost();

    $baseSlug = $slug;
    $counter = 1;
    while (\App\Models\Application::where('fqdn', 'like', "%{$slug}.{$host}%")->exists()) {
        $counter++;
        $slug = "{$baseSlug}-{$counter}";
    }

    return $slug;
}

/**
 * Generate a URL using sslip.io for a server.
 */
function generateUrl(Server $server, string $random, bool $forceHttps = false): string
{
    $wildcard = resolveWildcardDomain($server);
    $url = Url::fromString($wildcard);
    $host = $url->getHost();
    $path = $url->getPath() === '/' ? '' : $url->getPath();
    $scheme = $url->getScheme();
    if ($forceHttps) {
        $scheme = 'https';
    }

    return "$scheme://{$random}.$host$path";
}

/**
 * Generate an FQDN using sslip.io for a server.
 */
function generateFqdn(Server $server, string $random, bool $forceHttps = false, int $parserVersion = 5): string
{
    $wildcard = resolveWildcardDomain($server);
    $url = Url::fromString($wildcard);
    $host = $url->getHost();
    $path = $url->getPath() === '/' ? '' : $url->getPath();
    $scheme = $url->getScheme();
    if ($forceHttps) {
        $scheme = 'https';
    }

    if ($parserVersion >= 5 && version_compare(config('constants.saturn.version'), '4.0.0-beta.420.7', '>=')) {
        return "{$random}.$host$path";
    }

    return "$scheme://{$random}.$host$path";
}

/**
 * Get the sslip.io domain for a server.
 */
function sslip(Server $server)
{
    if (isDev() && $server->id === 0) {
        return 'http://127.0.0.1.sslip.io';
    }
    if ($server->ip === 'host.docker.internal') {
        $baseIp = base_ip();

        return "http://$baseIp.sslip.io";
    }
    // ipv6
    if (str($server->ip)->contains(':')) {
        $ipv6 = str($server->ip)->replace(':', '-');

        return "http://{$ipv6}.sslip.io";
    }

    return "http://{$server->ip}.sslip.io";
}

/**
 * Validate DNS entry for an FQDN against a server's IP.
 */
function validateDNSEntry(string $fqdn, Server $server)
{
    // https://www.cloudflare.com/ips-v4/#
    $cloudflare_ips = collect(['173.245.48.0/20', '103.21.244.0/22', '103.22.200.0/22', '103.31.4.0/22', '141.101.64.0/18', '108.162.192.0/18', '190.93.240.0/20', '188.114.96.0/20', '197.234.240.0/22', '198.41.128.0/17', '162.158.0.0/15', '104.16.0.0/13', '172.64.0.0/13', '131.0.72.0/22']);

    $url = Url::fromString($fqdn);
    $host = $url->getHost();
    if (str($host)->contains('sslip.io')) {
        return true;
    }
    $settings = instanceSettings();
    $is_dns_validation_enabled = data_get($settings, 'is_dns_validation_enabled');
    if (! $is_dns_validation_enabled) {
        return true;
    }
    $dns_servers = data_get($settings, 'custom_dns_servers');
    $dns_servers = str($dns_servers)->explode(',');
    if ($server->id === 0) {
        $ip = data_get($settings, 'public_ipv4', data_get($settings, 'public_ipv6', $server->ip));
    } else {
        $ip = $server->ip;
    }
    $found_matching_ip = false;
    $type = \PurplePixie\PhpDns\DNSTypes::NAME_A;
    foreach ($dns_servers as $dns_server) {
        try {
            $query = new DNSQuery($dns_server);
            $results = $query->query($host, $type);
            if ($results === false || $query->hasError()) {
                ray('Error: '.$query->getLasterror());
            } else {
                foreach ($results as $result) {
                    if ($result->getType() == $type) {
                        if (ipMatch($result->getData(), $cloudflare_ips->toArray(), $match)) {
                            $found_matching_ip = true;
                            break;
                        }
                        if ($result->getData() === $ip) {
                            $found_matching_ip = true;
                            break;
                        }
                    }
                }
            }
        } catch (\Exception) {
        }
    }

    return $found_matching_ip;
}

/**
 * Check if an IP address matches any of the given CIDR ranges.
 */
function ipMatch($ip, $cidrs, &$match = null)
{
    foreach ((array) $cidrs as $cidr) {
        [$subnet, $mask] = explode('/', $cidr);
        if (((ip2long($ip) & ($mask = ~((1 << (32 - $mask)) - 1))) == (ip2long($subnet) & $mask))) {
            $match = $cidr;

            return true;
        }
    }

    return false;
}

/**
 * Check if an IP address is in the allowlist.
 */
function checkIPAgainstAllowlist($ip, $allowlist)
{
    if (empty($allowlist)) {
        return false;
    }

    foreach ((array) $allowlist as $allowed) {
        $allowed = trim($allowed);

        if (empty($allowed)) {
            continue;
        }

        // Check if it's a CIDR notation
        if (str_contains($allowed, '/')) {
            [$subnet, $mask] = explode('/', $allowed);

            // Special case: 0.0.0.0 with any subnet means allow all
            if ($subnet === '0.0.0.0') {
                return true;
            }

            $mask = (int) $mask;

            // Validate mask
            if ($mask < 0 || $mask > 32) {
                continue;
            }

            // Calculate network addresses
            $ip_long = ip2long($ip);
            $subnet_long = ip2long($subnet);

            if ($ip_long === false || $subnet_long === false) {
                continue;
            }

            $mask_long = ~((1 << (32 - $mask)) - 1);

            if (($ip_long & $mask_long) == ($subnet_long & $mask_long)) {
                return true;
            }
        } else {
            // Special case: 0.0.0.0 means allow all
            if ($allowed === '0.0.0.0') {
                return true;
            }

            // Direct IP comparison
            if ($ip === $allowed) {
                return true;
            }
        }
    }

    return false;
}

/**
 * Get public IPv4 and IPv6 addresses and update instance settings.
 */
function get_public_ips()
{
    try {
        [$first, $second] = Process::concurrently(function (Pool $pool) {
            $pool->path(__DIR__)->command('curl -4s https://ifconfig.io');
            $pool->path(__DIR__)->command('curl -6s https://ifconfig.io');
        });
        $ipv4 = $first->output();
        if ($ipv4) {
            $ipv4 = trim($ipv4);
            $validate_ipv4 = filter_var($ipv4, FILTER_VALIDATE_IP);
            if ($validate_ipv4 == false) {
                echo "Invalid ipv4: $ipv4\n";

                return;
            }
            InstanceSettings::get()->update(['public_ipv4' => $ipv4]);
        }
    } catch (\Exception $e) {
        echo "Error: {$e->getMessage()}\n";
    }
    try {
        $ipv6 = $second->output();
        if ($ipv6) {
            $ipv6 = trim($ipv6);
            $validate_ipv6 = filter_var($ipv6, FILTER_VALIDATE_IP);
            if ($validate_ipv6 == false) {
                echo "Invalid ipv6: $ipv6\n";

                return;
            }
            InstanceSettings::get()->update(['public_ipv6' => $ipv6]);
        }
    } catch (\Throwable $e) {
        echo "Error: {$e->getMessage()}\n";
    }
}

/**
 * Get the realtime port for WebSocket connections.
 */
function getRealtime()
{
    $envDefined = config('constants.pusher.port');
    if (empty($envDefined)) {
        $url = Url::fromString(Request::getSchemeAndHttpHost());
        $port = $url->getPort();
        if ($port) {
            return '6001';
        } else {
            return null;
        }
    } else {
        return $envDefined;
    }
}

/**
 * Check if sslip.io domains are being used and return a warning.
 */
function sslipDomainWarning(string $domains)
{
    if (str($domains)->contains('sslip.io')) {
        return 'You are using sslip.io domains, which is not recommended for production use.';
    }

    return null;
}
