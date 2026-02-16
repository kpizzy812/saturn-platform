<?php

namespace App\Notifications\Channels;

use App\Models\PushoverNotificationSettings;

interface SendsPushover
{
    public function routeNotificationForPushover();

    public function getPushoverNotificationSettings(): ?PushoverNotificationSettings;
}
