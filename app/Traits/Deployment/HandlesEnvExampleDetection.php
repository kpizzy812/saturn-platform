<?php

namespace App\Traits\Deployment;

use App\Services\EnvExampleParser;

/**
 * Trait for detecting and importing environment variables from .env.example files.
 *
 * Required properties from parent class:
 * - $deployment_uuid, $workdir, $application, $application_deployment_queue
 * - $saved_outputs, $pull_request_id
 *
 * Required methods from parent class:
 * - execute_remote_command()
 */
trait HandlesEnvExampleDetection
{
    /**
     * Detect and import environment variables from .env.example after git clone.
     */
    private function detectAndImportEnvExample(): void
    {
        $envFiles = ['.env.example', '.env.sample', '.env.template'];
        $content = null;
        $sourceTemplate = null;

        foreach ($envFiles as $file) {
            $this->execute_remote_command(
                [
                    executeInDocker($this->deployment_uuid, "cat {$this->workdir}/{$file} 2>/dev/null || echo ''"),
                    'hidden' => true,
                    'save' => 'env_example_content',
                    'ignore_errors' => true,
                ]
            );

            $rawContent = $this->saved_outputs->get('env_example_content');
            if (! empty($rawContent) && trim($rawContent) !== '') {
                $content = $rawContent;
                $sourceTemplate = str_replace(['.env.', '.'], ['', '_'], $file); // env_example, env_sample, env_template
                break;
            }
        }

        if ($content === null) {
            return;
        }

        $parsed = EnvExampleParser::parse($content);
        if (empty($parsed)) {
            return;
        }

        $framework = EnvExampleParser::detectFramework(array_column($parsed, 'key'));
        $isPreview = $this->pull_request_id !== 0;

        // Get existing variable keys
        $existingKeys = $this->application->environment_variables()
            ->where('is_preview', $isPreview)
            ->pluck('key')
            ->map(fn ($k) => strtoupper($k))
            ->toArray();

        $created = [];
        $skipped = [];
        $required = [];

        foreach ($parsed as $var) {
            $key = $var['key'];

            if (in_array(strtoupper($key), $existingKeys, true)) {
                $skipped[] = $key;

                continue;
            }

            $this->application->environment_variables()->create([
                'key' => $key,
                'value' => $var['value'] ?? '',
                'is_runtime' => true,
                'is_buildtime' => true,
                'is_preview' => $isPreview,
                'is_required' => $var['is_required'],
                'source_template' => $sourceTemplate,
            ]);

            $created[] = $key;
            if ($var['is_required']) {
                $required[] = $key;
            }
        }

        // Log results
        if (! empty($created) || ! empty($skipped)) {
            $this->application_deployment_queue->addLogEntry('----------------------------------------');
            $this->application_deployment_queue->addLogEntry(
                'Detected env template with '.count($parsed).' variables'.
                ($framework ? " (framework: {$framework})" : '').'.'
            );

            if (! empty($created)) {
                $this->application_deployment_queue->addLogEntry(
                    'Created '.count($created).' new environment variables from template.'
                );
            }

            if (! empty($skipped)) {
                $this->application_deployment_queue->addLogEntry(
                    'Skipped '.count($skipped).' variables (already defined by user).'
                );
            }

            if (! empty($required)) {
                $this->application_deployment_queue->addLogEntry(
                    'Required variables needing values: '.implode(', ', $required)
                );
            }
            $this->application_deployment_queue->addLogEntry('----------------------------------------');
        }
    }
}
