<?php

namespace App\Livewire\Project\Application;

use App\Jobs\ApplicationDeploymentJob;
use Livewire\Component;

class General extends Component
{
    public ?string $baseDirectory = '/';

    public ?string $dockerComposeLocation = '/docker-compose.yaml';

    public ?string $dockerComposeCustomBuildCommand = null;

    public ?string $dockerComposeCustomStartCommand = null;

    /**
     * Get the preview of the docker compose build command with injected flags.
     */
    public function getDockerComposeBuildCommandPreviewProperty(): string
    {
        if (empty($this->dockerComposeCustomBuildCommand)) {
            return '';
        }

        $composePath = $this->getComposeFilePath();
        $envPath = ApplicationDeploymentJob::BUILD_TIME_ENV_PATH;

        return $this->injectDockerComposeFlags(
            $this->dockerComposeCustomBuildCommand,
            $composePath,
            $envPath
        );
    }

    /**
     * Get the preview of the docker compose start command with injected flags.
     */
    public function getDockerComposeStartCommandPreviewProperty(): string
    {
        if (empty($this->dockerComposeCustomStartCommand)) {
            return '';
        }

        $composePath = $this->getComposeFilePath();
        $envPath = '{workdir}/.env';

        return $this->injectDockerComposeFlags(
            $this->dockerComposeCustomStartCommand,
            $composePath,
            $envPath
        );
    }

    /**
     * Get the full compose file path combining base directory and compose location.
     */
    protected function getComposeFilePath(): string
    {
        $baseDir = rtrim($this->baseDirectory ?? '/', '/');

        // Handle root directory - don't add extra slash
        if ($baseDir === '' || $baseDir === '/') {
            return '.'.$this->dockerComposeLocation;
        }

        return '.'.$baseDir.$this->dockerComposeLocation;
    }

    /**
     * Inject -f and --env-file flags into docker compose command if not present.
     */
    protected function injectDockerComposeFlags(string $command, string $composePath, string $envPath): string
    {
        // Check if -f or --file flag is already present
        $hasFileFlag = preg_match('/(?:^|\s)-f(?:\s|=|$)|(?:^|\s)--file(?:\s|=|$)/', $command);

        // Check if --env-file flag is already present
        $hasEnvFileFlag = preg_match('/(?:^|\s)--env-file(?:\s|=|$)/', $command);

        $injection = '';

        if (! $hasFileFlag) {
            $injection .= " -f {$composePath}";
        }

        if (! $hasEnvFileFlag) {
            $injection .= " --env-file {$envPath}";
        }

        if (empty($injection)) {
            return $command;
        }

        // Find position to inject (after "docker compose" or "docker-compose")
        $pattern = '/(docker[\s-]compose)/i';
        if (preg_match($pattern, $command, $matches, PREG_OFFSET_CAPTURE)) {
            $pos = $matches[0][1] + strlen($matches[0][0]);

            return substr($command, 0, $pos).$injection.substr($command, $pos);
        }

        return $command.$injection;
    }

    public function render()
    {
        return view('livewire.project.application.general');
    }
}
