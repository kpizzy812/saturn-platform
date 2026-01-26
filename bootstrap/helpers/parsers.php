<?php

use App\Models\Application;
use App\Models\Service;
use App\Parsers\DockerVolumeParser;
use Illuminate\Support\Collection;

/**
 * Validates a Docker Compose YAML string for command injection vulnerabilities.
 * This should be called BEFORE saving to database to prevent malicious data from being stored.
 *
 * @param  string  $composeYaml  The raw Docker Compose YAML content
 *
 * @throws \Exception If the compose file contains command injection attempts
 *
 * @see DockerVolumeParser::validateComposeForInjection()
 */
function validateDockerComposeForInjection(string $composeYaml): void
{
    DockerVolumeParser::validateComposeForInjection($composeYaml);
}

/**
 * Validates a Docker volume string (format: "source:target" or "source:target:mode")
 *
 * @param  string  $volumeString  The volume string to validate
 *
 * @throws \Exception If the volume string contains command injection attempts
 *
 * @see DockerVolumeParser::validateVolumeStringForInjection()
 */
function validateVolumeStringForInjection(string $volumeString): void
{
    DockerVolumeParser::validateVolumeStringForInjection($volumeString);
}

/**
 * Parses a Docker volume string into its components.
 *
 * @param  string  $volumeString  The volume string to parse
 * @return array{source: \Illuminate\Support\Stringable|null, target: \Illuminate\Support\Stringable|null, mode: \Illuminate\Support\Stringable|null}
 *
 * @throws \Exception If the volume string contains command injection attempts
 *
 * @see DockerVolumeParser::parseVolumeString()
 */
function parseDockerVolumeString(string $volumeString): array
{
    return DockerVolumeParser::parseVolumeString($volumeString);
}

/**
 * Parse a Docker Compose file for an Application resource.
 *
 * @param  Application  $resource  The application resource
 * @param  int  $pull_request_id  Pull request ID (0 for non-PR deployments)
 * @param  int|null  $preview_id  Preview deployment ID
 * @param  string|null  $commit  Commit hash for image tagging
 * @return Collection The parsed compose configuration
 *
 * @see \App\Parsers\ApplicationComposeParser::parse()
 */
function applicationParser(Application $resource, int $pull_request_id = 0, ?int $preview_id = null, ?string $commit = null): Collection
{
    return \App\Parsers\ApplicationComposeParser::parse($resource, $pull_request_id, $preview_id, $commit);
}

/**
 * Parse a Docker Compose file for a Service resource.
 *
 * @param  Service  $resource  The service resource
 * @return Collection The parsed compose configuration
 *
 * @see \App\Parsers\ServiceComposeParser::parse()
 */
function serviceParser(Service $resource): Collection
{
    return \App\Parsers\ServiceComposeParser::parse($resource);
}
