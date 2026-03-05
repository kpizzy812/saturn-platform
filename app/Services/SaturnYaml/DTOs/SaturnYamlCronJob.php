<?php

namespace App\Services\SaturnYaml\DTOs;

class SaturnYamlCronJob
{
    public function __construct(
        public string $name,
        public string $command,
        public string $schedule,
        public ?string $container = null,
        public int $timeout = 3600,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'command' => $this->command,
            'schedule' => $this->schedule,
            'container' => $this->container,
            'timeout' => $this->timeout,
        ];
    }
}
