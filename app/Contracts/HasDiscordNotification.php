<?php

namespace App\Contracts;

use App\Notifications\Dto\DiscordMessage;

interface HasDiscordNotification
{
    public function toDiscord(): DiscordMessage;
}
