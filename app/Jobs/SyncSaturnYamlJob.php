<?php

namespace App\Jobs;

use App\Models\Environment;
use App\Services\SaturnYaml\SaturnYamlParser;
use App\Services\SaturnYaml\SaturnYamlReconciler;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SyncSaturnYamlJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 120;

    public function __construct(
        public int $environmentId,
        public string $yamlContent,
        public ?string $triggeredBy = null,
    ) {
        $this->queue = 'high';
    }

    public function handle(): void
    {
        $environment = Environment::find($this->environmentId);
        if (! $environment) {
            Log::warning("SyncSaturnYamlJob: Environment {$this->environmentId} not found.");

            return;
        }

        $parser = new SaturnYamlParser;
        $reconciler = new SaturnYamlReconciler;

        // 1. Validate
        $errors = $parser->validate($this->yamlContent);
        if (! empty($errors)) {
            Log::error('SyncSaturnYamlJob: Validation errors in saturn.yaml', [
                'environment' => $environment->name,
                'errors' => $errors,
            ]);

            return;
        }

        // 2. Parse
        try {
            $config = $parser->parse($this->yamlContent);
        } catch (\Exception $e) {
            Log::error("SyncSaturnYamlJob: Parse error: {$e->getMessage()}");

            return;
        }

        // 3. Check if changed
        if ($config->hash() === $environment->saturn_yaml_hash) {
            Log::info('SyncSaturnYamlJob: No changes detected in saturn.yaml', [
                'environment' => $environment->name,
            ]);

            return;
        }

        // 4. Reconcile
        try {
            $plan = $reconciler->reconcile($config, $environment);

            Log::info('SyncSaturnYamlJob: Reconciliation completed', [
                'environment' => $environment->name,
                'actions' => count($plan->actions),
                'warnings' => $plan->warnings,
                'triggered_by' => $this->triggeredBy,
            ]);
        } catch (\Exception $e) {
            Log::error("SyncSaturnYamlJob: Reconciliation failed: {$e->getMessage()}", [
                'environment' => $environment->name,
            ]);

            throw $e;
        }
    }
}
