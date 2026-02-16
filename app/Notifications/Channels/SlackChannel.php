<?php

namespace App\Notifications\Channels;

use App\Contracts\HasSlackNotification;
use App\Jobs\SendMessageToSlackJob;
use Illuminate\Notifications\Notification;

class SlackChannel
{
    /**
     * Send the given notification.
     *
     * @param  Notification&HasSlackNotification  $notification
     */
    public function send(SendsSlack $notifiable, Notification $notification): void
    {
        $message = $notification->toSlack();
        $slackSettings = $notifiable->slackNotificationSettings;

        if (! $slackSettings || ! $slackSettings->isEnabled() || ! $slackSettings->slack_webhook_url) {
            return;
        }

        SendMessageToSlackJob::dispatch($message, $slackSettings->slack_webhook_url);
    }
}
