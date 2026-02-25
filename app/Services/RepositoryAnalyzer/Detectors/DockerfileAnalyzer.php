<?php

namespace App\Services\RepositoryAnalyzer\Detectors;

use App\Services\RepositoryAnalyzer\DTOs\DockerfileInfo;

/**
 * Analyzes Dockerfile to extract configuration information
 */
class DockerfileAnalyzer
{
    private const MAX_FILE_SIZE = 512 * 1024; // 512KB

    /**
     * Analyze Dockerfile to extract configuration
     */
    public function analyze(string $appPath): ?DockerfileInfo
    {
        $dockerfile = $this->findDockerfile($appPath);
        if ($dockerfile === null) {
            return null;
        }

        return $this->analyzeFile($dockerfile);
    }

    /**
     * Analyze a specific Dockerfile by its full path
     */
    public function analyzeFile(string $filePath): ?DockerfileInfo
    {
        if (! $this->isReadableFile($filePath)) {
            return null;
        }

        $content = file_get_contents($filePath);

        return new DockerfileInfo(
            baseImage: $this->extractBaseImage($content),
            envVariables: $this->extractEnvVariables($content),
            exposedPorts: $this->extractExposedPorts($content),
            buildArgs: $this->extractBuildArgs($content),
            workdir: $this->extractWorkdir($content),
            healthcheck: $this->extractHealthcheck($content),
            entrypoint: $this->extractEntrypoint($content),
            cmd: $this->extractCmd($content),
            labels: $this->extractLabels($content),
        );
    }

    /**
     * Find Dockerfile in the app directory
     */
    private function findDockerfile(string $appPath): ?string
    {
        $candidates = [
            'Dockerfile',
            'dockerfile',
            'Dockerfile.prod',
            'Dockerfile.production',
        ];

        foreach ($candidates as $candidate) {
            $path = $appPath.'/'.$candidate;
            if ($this->isReadableFile($path)) {
                return $path;
            }
        }

        return null;
    }

    /**
     * Extract base image from FROM instruction
     */
    private function extractBaseImage(string $content): ?string
    {
        // Match FROM instruction (possibly with AS alias)
        // FROM node:18-alpine AS builder
        // FROM --platform=linux/amd64 node:18
        if (preg_match('/^FROM\s+(?:--platform=\S+\s+)?(\S+)/im', $content, $matches)) {
            return $matches[1];
        }

        return null;
    }

    /**
     * Extract environment variables from ENV instructions
     *
     * @return array<string, string|null>
     */
    private function extractEnvVariables(string $content): array
    {
        $envVars = [];

        // Match ENV KEY=value or ENV KEY value formats
        // ENV NODE_ENV=production
        // ENV PORT 3000
        // ENV KEY1=value1 KEY2=value2
        preg_match_all('/^ENV\s+(.+)$/im', $content, $matches);

        foreach ($matches[1] as $line) {
            $line = trim($line);

            // Handle KEY=value format (can be multiple on one line)
            if (str_contains($line, '=')) {
                // Parse KEY=value pairs
                preg_match_all('/(\w+)=(?:"([^"]*)"|\'([^\']*)\'|(\S*))/', $line, $pairs);
                foreach ($pairs[1] as $i => $key) {
                    $value = $pairs[2][$i] ?: $pairs[3][$i] ?: $pairs[4][$i];
                    $envVars[$key] = $value ?: null;
                }
            } else {
                // Handle KEY value format
                $parts = preg_split('/\s+/', $line, 2);
                if (count($parts) === 2) {
                    $envVars[$parts[0]] = trim($parts[1], '"\'');
                }
            }
        }

        return $envVars;
    }

    /**
     * Extract exposed ports from EXPOSE instructions
     *
     * @return int[]
     */
    private function extractExposedPorts(string $content): array
    {
        $ports = [];

        // Match EXPOSE instruction
        // EXPOSE 3000
        // EXPOSE 80 443
        // EXPOSE 8080/tcp
        preg_match_all('/^EXPOSE\s+(.+)$/im', $content, $matches);

        foreach ($matches[1] as $line) {
            $portSpecs = preg_split('/\s+/', trim($line));
            foreach ($portSpecs as $spec) {
                // Remove protocol suffix if present
                $port = (int) preg_replace('/\/\w+$/', '', $spec);
                if ($port > 0 && $port <= 65535) {
                    $ports[] = $port;
                }
            }
        }

        return array_unique($ports);
    }

    /**
     * Extract build arguments from ARG instructions
     *
     * @return array<string, string|null>
     */
    private function extractBuildArgs(string $content): array
    {
        $args = [];

        // Match ARG instruction
        // ARG NODE_VERSION=18
        // ARG BUILD_DATE
        preg_match_all('/^ARG\s+(\w+)(?:=(.*))?$/im', $content, $matches);

        foreach ($matches[1] as $i => $key) {
            $value = isset($matches[2][$i]) ? trim($matches[2][$i], '"\'') : null;
            $args[$key] = $value ?: null;
        }

        return $args;
    }

    /**
     * Extract working directory from WORKDIR instruction
     */
    private function extractWorkdir(string $content): ?string
    {
        // Get the last WORKDIR (most relevant)
        if (preg_match_all('/^WORKDIR\s+(.+)$/im', $content, $matches)) {
            $workdirs = $matches[1];

            return trim(end($workdirs));
        }

        return null;
    }

    /**
     * Extract healthcheck command
     */
    private function extractHealthcheck(string $content): ?string
    {
        // Match HEALTHCHECK instruction
        // HEALTHCHECK --interval=30s CMD curl -f http://localhost/health
        if (preg_match('/^HEALTHCHECK\s+(?:--\w+[=\s]+\S+\s+)*CMD\s+(.+)/im', $content, $matches)) {
            return trim($matches[1]);
        }

        return null;
    }

    /**
     * Extract entrypoint command
     */
    private function extractEntrypoint(string $content): ?string
    {
        // Match ENTRYPOINT instruction (last one wins)
        // ENTRYPOINT ["node", "server.js"]
        // ENTRYPOINT node server.js
        if (preg_match_all('/^ENTRYPOINT\s+(.+)$/im', $content, $matches)) {
            $entrypoints = $matches[1];
            $entrypoint = trim(end($entrypoints));

            // Parse JSON array format
            if (str_starts_with($entrypoint, '[')) {
                return $this->parseJsonArray($entrypoint);
            }

            return $entrypoint;
        }

        return null;
    }

    /**
     * Extract CMD instruction
     */
    private function extractCmd(string $content): ?string
    {
        // Match CMD instruction (last one wins)
        // CMD ["npm", "start"]
        // CMD npm start
        if (preg_match_all('/^CMD\s+(.+)$/im', $content, $matches)) {
            $cmds = $matches[1];
            $cmd = trim(end($cmds));

            // Parse JSON array format
            if (str_starts_with($cmd, '[')) {
                return $this->parseJsonArray($cmd);
            }

            return $cmd;
        }

        return null;
    }

    /**
     * Extract labels from LABEL instructions
     *
     * @return array<string, string>
     */
    private function extractLabels(string $content): array
    {
        $labels = [];

        // Match LABEL instruction
        // LABEL version="1.0"
        // LABEL maintainer="admin@example.com"
        preg_match_all('/^LABEL\s+(.+)$/im', $content, $matches);

        foreach ($matches[1] as $line) {
            // Parse key=value pairs
            preg_match_all('/(\S+)=(?:"([^"]*)"|\'([^\']*)\'|(\S*))/', $line, $pairs);
            foreach ($pairs[1] as $i => $key) {
                $value = $pairs[2][$i] ?: $pairs[3][$i] ?: $pairs[4][$i];
                $labels[$key] = $value;
            }
        }

        return $labels;
    }

    /**
     * Parse JSON array format to shell command
     */
    private function parseJsonArray(string $jsonArray): string
    {
        try {
            $parsed = json_decode($jsonArray, true, 512, JSON_THROW_ON_ERROR);
            if (is_array($parsed)) {
                return implode(' ', $parsed);
            }
        } catch (\JsonException) {
            // Fall through to return original
        }

        // Remove brackets and quotes for display
        return preg_replace('/[\[\]"]/', '', $jsonArray);
    }

    /**
     * Check if file is readable and within size limit
     */
    private function isReadableFile(string $path): bool
    {
        return file_exists($path)
            && is_file($path)
            && is_readable($path)
            && filesize($path) <= self::MAX_FILE_SIZE;
    }
}
