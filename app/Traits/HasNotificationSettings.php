<?php

namespace App\Traits;

use App\Notifications\Channels\DiscordChannel;
use App\Notifications\Channels\EmailChannel;
use App\Notifications\Channels\InAppChannel;
use App\Notifications\Channels\PushoverChannel;
use App\Notifications\Channels\SlackChannel;
use App\Notifications\Channels\TelegramChannel;
use App\Notifications\Channels\WebhookChannel;
use Illuminate\Database\Eloquent\Model;

trait HasNotificationSettings
{
    protected $alwaysSendEvents = [
        'server_force_enabled',
        'server_force_disabled',
        'general',
        'test',
        'ssl_certificate_renewal',
        'hetzner_deletion_failure',
    ];

    /**
     * Events that should always create in-app notifications.
     * NOTE: 'general' and 'test' are excluded - system notifications go to admin logs.
     */
    protected $inAppEvents = [
        'deployment_success',
        'deployment_failure',
        'deployment_approval_required',
        'backup_success',
        'backup_failure',
        'server_unreachable',
        'server_reachable',
        'server_disk_usage',
        'status_change',
        'security_alert',
        'ssl_certificate_renewal',
    ];

    /**
     * Get settings model for specific channel
     */
    public function getNotificationSettings(string $channel): ?Model
    {
        return match ($channel) {
            'email' => $this->emailNotificationSettings,
            'discord' => $this->discordNotificationSettings,
            'telegram' => $this->telegramNotificationSettings,
            'slack' => $this->slackNotificationSettings,
            'pushover' => $this->pushoverNotificationSettings,
            'webhook' => $this->webhookNotificationSettings,
            default => null,
        };
    }

    /**
     * Check if a notification channel is enabled
     */
    public function isNotificationEnabled(string $channel): bool
    {
        $settings = $this->getNotificationSettings($channel);

        return $settings?->isEnabled() ?? false;
    }

    /**
     * Check if a specific notification type is enabled for a channel
     */
    public function isNotificationTypeEnabled(string $channel, string $event): bool
    {
        $settings = $this->getNotificationSettings($channel);

        if (! $settings || ! $this->isNotificationEnabled($channel)) {
            return false;
        }

        if (in_array($event, $this->alwaysSendEvents)) {
            return true;
        }

        $settingKey = "{$event}_{$channel}_notifications";

        return (bool) $settings->$settingKey;
    }

    /**
     * Get all enabled notification channels for an event
     */
    public function getEnabledChannels(string $event): array
    {
        $channels = [];

        // Add InAppChannel for user-facing events and system events (for admin panel)
        // System events (general, test) will be filtered in the UI - shown only in admin panel
        if (in_array($event, $this->inAppEvents) || in_array($event, ['general', 'test'])) {
            $channels[] = InAppChannel::class;
        }

        $channelMap = [
            'email' => EmailChannel::class,
            'discord' => DiscordChannel::class,
            'telegram' => TelegramChannel::class,
            'slack' => SlackChannel::class,
            'pushover' => PushoverChannel::class,
            'webhook' => WebhookChannel::class,
        ];

        if ($event === 'general') {
            unset($channelMap['email']);
        }

        foreach ($channelMap as $channel => $channelClass) {
            if ($this->isNotificationEnabled($channel) && $this->isNotificationTypeEnabled($channel, $event)) {
                $channels[] = $channelClass;
            }
        }

        return $channels;
    }
}
