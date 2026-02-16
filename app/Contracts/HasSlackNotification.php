<?php

namespace App\Contracts;

use App\Notifications\Dto\SlackMessage;

interface HasSlackNotification
{
    public function toSlack(): SlackMessage;
}
