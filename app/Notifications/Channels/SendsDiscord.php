<?php

namespace App\Notifications\Channels;

use App\Models\DiscordNotificationSettings;

/**
 * @property-read DiscordNotificationSettings|null $discordNotificationSettings
 */
interface SendsDiscord
{
    public function routeNotificationForDiscord();
}
