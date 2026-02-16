<?php

namespace App\Contracts;

interface HasTelegramNotification
{
    /**
     * @return array{message: string, buttons: array<int, array{text: string, url: string}>}
     */
    public function toTelegram(): array;
}
