<?php

namespace App\Contracts;

interface NotificationSettingsContract
{
    public function isEnabled(): bool;
}
