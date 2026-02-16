<?php

namespace App\Contracts;

use Illuminate\Notifications\Messages\MailMessage;

interface HasMailNotification
{
    public function toMail(object $notifiable): MailMessage;
}
