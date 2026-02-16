<?php

namespace App\Notifications\Channels;

use App\Models\SlackNotificationSettings;

/**
 * @property-read SlackNotificationSettings|null $slackNotificationSettings
 */
interface SendsSlack
{
    public function routeNotificationForSlack();
}
