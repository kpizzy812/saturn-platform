<?php

namespace App\Services\SaturnYaml;

use App\Models\Application;
use App\Models\Environment;
use Illuminate\Support\Facades\Log;

/**
 * Detects saturn.yaml in a repository and determines if sync is needed.
 */
class SaturnYamlDetector
{
    private const YAML_FILENAMES = ['saturn.yaml', '.saturn.yaml', 'saturn.yml', '.saturn.yml'];

    /**
     * Check if any saturn.yaml file exists in the given file list.
     *
     * @param  array<int, string>  $files  List of files in the repository
     */
    public function detect(array $files, ?string $baseDirectory = null): ?string
    {
        $prefix = $baseDirectory ? rtrim($baseDirectory, '/').'/' : '';

        foreach (self::YAML_FILENAMES as $filename) {
            $path = $prefix.$filename;
            if (in_array($path, $files, true) || in_array($filename, $files, true)) {
                return $filename;
            }
        }

        return null;
    }

    /**
     * Check if a saturn.yaml content has changed compared to the stored hash.
     */
    public function hasChanged(string $yamlContent, Environment $environment): bool
    {
        $parser = new SaturnYamlParser;

        try {
            $config = $parser->parse($yamlContent);
        } catch (\Exception $e) {
            Log::warning("Failed to parse saturn.yaml: {$e->getMessage()}");

            return false;
        }

        return $config->hash() !== $environment->saturn_yaml_hash;
    }

    /**
     * Get the expected saturn.yaml path for a given application.
     */
    public function getYamlPath(Application $application): string
    {
        $baseDir = rtrim($application->base_directory ?? '/', '/');
        $filename = self::YAML_FILENAMES[0];

        return $baseDir === '/' ? $filename : "{$baseDir}/{$filename}";
    }

    /**
     * List of valid saturn.yaml filenames.
     *
     * @return array<int, string>
     */
    public static function filenames(): array
    {
        return self::YAML_FILENAMES;
    }
}
