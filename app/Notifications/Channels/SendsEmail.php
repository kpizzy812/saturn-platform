<?php

namespace App\Notifications\Channels;

use App\Models\EmailNotificationSettings;

interface SendsEmail
{
    public function getRecipients(): array;

    public function getEmailNotificationSettings(): ?EmailNotificationSettings;
}
