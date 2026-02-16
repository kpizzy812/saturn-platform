<?php

namespace App\Notifications\Channels;

use App\Models\TelegramNotificationSettings;

/**
 * @property-read TelegramNotificationSettings|null $telegramNotificationSettings
 */
interface SendsTelegram
{
    public function routeNotificationForTelegram();
}
