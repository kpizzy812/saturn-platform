<?php

namespace App\Contracts;

use App\Notifications\Dto\PushoverMessage;

interface HasPushoverNotification
{
    public function toPushover(): PushoverMessage;
}
