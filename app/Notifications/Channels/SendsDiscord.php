<?php

namespace App\Notifications\Channels;

use App\Models\DiscordNotificationSettings;

interface SendsDiscord
{
    public function routeNotificationForDiscord();

    public function getDiscordNotificationSettings(): ?DiscordNotificationSettings;
}
