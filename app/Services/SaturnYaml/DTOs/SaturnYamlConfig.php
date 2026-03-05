<?php

namespace App\Services\SaturnYaml\DTOs;

class SaturnYamlConfig
{
    /**
     * @param  array<string, SaturnYamlApplication>  $applications
     * @param  array<string, SaturnYamlDatabase>  $databases
     * @param  array<string, SaturnYamlCronJob>  $cron
     * @param  array<string, string>  $sharedVariables
     */
    public function __construct(
        public string $version = '1',
        public array $applications = [],
        public array $databases = [],
        public array $cron = [],
        public array $sharedVariables = [],
    ) {}

    public function hash(): string
    {
        return hash('sha256', json_encode([
            'version' => $this->version,
            'applications' => array_map(fn ($a) => $a->toArray(), $this->applications),
            'databases' => array_map(fn ($d) => $d->toArray(), $this->databases),
            'cron' => array_map(fn ($c) => $c->toArray(), $this->cron),
            'shared_variables' => $this->sharedVariables,
        ]));
    }
}
