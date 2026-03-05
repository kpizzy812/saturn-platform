<?php

namespace App\Services\SaturnYaml\DTOs;

class SaturnYamlDatabase
{
    /**
     * @param  array{schedule?: string, retention?: int, s3_storage?: string}  $backups
     */
    public function __construct(
        public string $name,
        public string $type = 'postgresql',
        public ?string $version = null,
        public ?string $image = null,
        public bool $isPublic = false,
        public array $backups = [],
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'type' => $this->type,
            'version' => $this->version,
            'image' => $this->image,
            'is_public' => $this->isPublic,
            'backups' => $this->backups,
        ];
    }
}
