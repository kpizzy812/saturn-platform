<?php

namespace App\Notifications\Channels;

use App\Contracts\HasWebhookNotification;
use App\Jobs\SendWebhookJob;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;

class WebhookChannel
{
    /**
     * Send the given notification.
     *
     * @param  Notification&HasWebhookNotification  $notification
     */
    public function send($notifiable, Notification $notification): void
    {
        $webhookSettings = $notifiable->webhookNotificationSettings;

        if (! $webhookSettings || ! $webhookSettings->isEnabled() || ! $webhookSettings->webhook_url) {
            Log::debug('Webhook notification skipped - not enabled or no URL configured');

            return;
        }

        $payload = $notification->toWebhook();

        Log::debug('Dispatching webhook notification', [
            'notification' => get_class($notification),
            'url' => $webhookSettings->webhook_url,
        ]);

        SendWebhookJob::dispatch($payload, $webhookSettings->webhook_url);
    }
}
