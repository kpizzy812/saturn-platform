<?php

namespace App\Notifications\Channels;

use App\Contracts\HasDiscordNotification;
use App\Jobs\SendMessageToDiscordJob;
use Illuminate\Notifications\Notification;

class DiscordChannel
{
    /**
     * Send the given notification.
     *
     * @param  Notification&HasDiscordNotification  $notification
     */
    public function send(SendsDiscord $notifiable, Notification $notification): void
    {
        $message = $notification->toDiscord();

        $discordSettings = $notifiable->getDiscordNotificationSettings();

        if (! $discordSettings || ! $discordSettings->isEnabled() || ! $discordSettings->discord_webhook_url) {
            return;
        }

        if (! $discordSettings->discord_ping_enabled) {
            $message->isCritical = false;
        }

        SendMessageToDiscordJob::dispatch($message, $discordSettings->discord_webhook_url);
    }
}
