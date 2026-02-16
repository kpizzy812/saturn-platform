<?php

namespace App\Notifications\Channels;

use App\Models\EmailNotificationSettings;

/**
 * @property-read EmailNotificationSettings|null $emailNotificationSettings
 */
interface SendsEmail
{
    public function getRecipients(): array;
}
