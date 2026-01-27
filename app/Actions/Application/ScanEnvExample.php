<?php

namespace App\Actions\Application;

use App\Models\Application;
use App\Services\EnvExampleParser;
use Visus\Cuid2\Cuid2;

/**
 * Scan an application's git repository for .env.example files
 * and create missing environment variables.
 *
 * Uses sparse git checkout to fetch only the env template file.
 */
class ScanEnvExample
{
    /**
     * @return array{created: string[], skipped: string[], required: string[], framework: ?string, source_file: ?string}
     */
    public function handle(Application $application): array
    {
        $result = [
            'created' => [],
            'skipped' => [],
            'required' => [],
            'framework' => null,
            'source_file' => null,
        ];

        $server = $application->destination->server;
        if (! $server) {
            return $result;
        }

        $envFiles = ['.env.example', '.env.sample', '.env.template'];
        $content = null;
        $sourceFile = null;
        $sourceTemplate = null;

        $uuid = new Cuid2;
        $workdir = rtrim($application->base_directory, '/');

        ['commands' => $cloneCommand] = $application->generateGitImportCommands(
            deployment_uuid: $uuid,
            only_checkout: true,
            exec_in_docker: false,
            custom_base_dir: '.',
        );

        foreach ($envFiles as $file) {
            $filePath = ".{$workdir}/{$file}";

            $commands = collect([
                "rm -rf /tmp/{$uuid}",
                "mkdir -p /tmp/{$uuid}",
                "cd /tmp/{$uuid}",
                $cloneCommand,
                'git sparse-checkout init --cone',
                "git sparse-checkout set {$filePath}",
                'git read-tree -mu HEAD',
                "cat {$filePath} 2>/dev/null || echo ''",
            ]);

            try {
                $rawContent = instant_remote_process($commands, $server);
                if (! empty($rawContent) && trim($rawContent) !== '') {
                    $content = $rawContent;
                    $sourceFile = $file;
                    $sourceTemplate = str_replace(['.env.', '.'], ['', '_'], $file);
                    break;
                }
            } catch (\Exception) {
                continue;
            }
        }

        // Cleanup temp directory
        try {
            instant_remote_process(["rm -rf /tmp/{$uuid}"], $server, false);
        } catch (\Exception) {
            // Ignore cleanup errors
        }

        if ($content === null) {
            return $result;
        }

        $parsed = EnvExampleParser::parse($content);
        if (empty($parsed)) {
            return $result;
        }

        $result['framework'] = EnvExampleParser::detectFramework(array_column($parsed, 'key'));
        $result['source_file'] = $sourceFile;

        // Get existing variable keys
        $existingKeys = $application->environment_variables()
            ->where('is_preview', false)
            ->pluck('key')
            ->map(fn ($k) => strtoupper($k))
            ->toArray();

        foreach ($parsed as $var) {
            $key = $var['key'];

            if (in_array(strtoupper($key), $existingKeys, true)) {
                $result['skipped'][] = $key;

                continue;
            }

            $application->environment_variables()->create([
                'key' => $key,
                'value' => $var['value'] ?? '',
                'is_runtime' => true,
                'is_buildtime' => true,
                'is_preview' => false,
                'is_required' => $var['is_required'],
                'source_template' => $sourceTemplate,
            ]);

            $result['created'][] = $key;
            if ($var['is_required']) {
                $result['required'][] = $key;
            }
        }

        return $result;
    }
}
