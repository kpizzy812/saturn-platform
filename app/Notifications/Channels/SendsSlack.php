<?php

namespace App\Notifications\Channels;

use App\Models\SlackNotificationSettings;

interface SendsSlack
{
    public function routeNotificationForSlack();

    public function getSlackNotificationSettings(): ?SlackNotificationSettings;
}
