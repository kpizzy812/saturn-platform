<?php

namespace App\Services\SaturnYaml\DTOs;

class ReconciliationPlan
{
    /**
     * @param  array<int, array{action: string, type: string, name: string, details?: array<string, mixed>}>  $actions
     * @param  array<int, string>  $warnings
     */
    public function __construct(
        public array $actions = [],
        public array $warnings = [],
    ) {}

    public function addCreate(string $type, string $name, array $details = []): void
    {
        $this->actions[] = [
            'action' => 'create',
            'type' => $type,
            'name' => $name,
            'details' => $details,
        ];
    }

    public function addUpdate(string $type, string $name, array $details = []): void
    {
        $this->actions[] = [
            'action' => 'update',
            'type' => $type,
            'name' => $name,
            'details' => $details,
        ];
    }

    public function addSkip(string $type, string $name, string $reason = ''): void
    {
        $this->actions[] = [
            'action' => 'skip',
            'type' => $type,
            'name' => $name,
            'details' => ['reason' => $reason],
        ];
    }

    public function addWarning(string $message): void
    {
        $this->warnings[] = $message;
    }

    public function hasChanges(): bool
    {
        return collect($this->actions)->contains(fn ($a) => $a['action'] !== 'skip');
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'actions' => $this->actions,
            'warnings' => $this->warnings,
            'has_changes' => $this->hasChanges(),
        ];
    }
}
