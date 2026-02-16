<?php

namespace App\Contracts;

interface HasWebhookNotification
{
    /**
     * @return array<string, mixed>
     */
    public function toWebhook(): array;
}
