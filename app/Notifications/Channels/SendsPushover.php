<?php

namespace App\Notifications\Channels;

use App\Models\PushoverNotificationSettings;

/**
 * @property-read PushoverNotificationSettings|null $pushoverNotificationSettings
 */
interface SendsPushover
{
    public function routeNotificationForPushover();
}
