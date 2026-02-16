<?php

namespace App\Notifications\Channels;

use App\Contracts\HasPushoverNotification;
use App\Jobs\SendMessageToPushoverJob;
use Illuminate\Notifications\Notification;

class PushoverChannel
{
    /**
     * @param  Notification&HasPushoverNotification  $notification
     */
    public function send(SendsPushover $notifiable, Notification $notification): void
    {
        $message = $notification->toPushover();
        $pushoverSettings = $notifiable->getPushoverNotificationSettings();

        if (! $pushoverSettings || ! $pushoverSettings->isEnabled() || ! $pushoverSettings->pushover_user_key || ! $pushoverSettings->pushover_api_token) {
            return;
        }

        SendMessageToPushoverJob::dispatch($message, $pushoverSettings->pushover_api_token, $pushoverSettings->pushover_user_key);
    }
}
