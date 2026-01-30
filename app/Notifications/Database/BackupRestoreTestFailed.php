<?php

namespace App\Notifications\Database;

use App\Notifications\CustomEmailNotification;
use App\Notifications\Dto\DiscordMessage;
use App\Notifications\Dto\PushoverMessage;
use App\Notifications\Dto\SlackMessage;
use Illuminate\Notifications\Messages\MailMessage;

class BackupRestoreTestFailed extends CustomEmailNotification
{
    public string $name;

    public function __construct(public $database, public string $errorMessage)
    {
        $this->onQueue('high');
        $this->name = $database->name;
    }

    public function via(object $notifiable): array
    {
        return $notifiable->getEnabledChannels('backup_failed');
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = new MailMessage;
        $mail->subject("Saturn Platform: Backup restore test FAILED for {$this->name}");
        $mail->error()
            ->line("The automated restore test for database **{$this->name}** has failed.")
            ->line("**Error:** {$this->errorMessage}")
            ->line('Please review your backup configuration and ensure backups are valid.');

        return $mail;
    }

    public function toDiscord(): DiscordMessage
    {
        $message = new DiscordMessage(
            title: ':x: Backup Restore Test Failed',
            description: "Automated restore test for **{$this->name}** has failed!",
            color: DiscordMessage::errorColor(),
        );

        $message->addField('Error', $this->errorMessage, false);

        return $message;
    }

    public function toTelegram(): array
    {
        return [
            'message' => "⚠️ Saturn Platform: Backup restore test for {$this->name} FAILED!\n\nError: {$this->errorMessage}",
        ];
    }

    public function toPushover(): PushoverMessage
    {
        return new PushoverMessage(
            title: 'Backup Restore Test Failed',
            level: 'error',
            message: "Restore test for {$this->name} failed: {$this->errorMessage}",
        );
    }

    public function toSlack(): SlackMessage
    {
        return new SlackMessage(
            title: 'Backup Restore Test Failed',
            description: "Automated restore test for *{$this->name}* has failed!\n\n*Error:* {$this->errorMessage}",
            color: SlackMessage::errorColor()
        );
    }

    public function toWebhook(): array
    {
        return [
            'success' => false,
            'message' => 'Backup restore test failed',
            'event' => 'backup_restore_test_failed',
            'database_name' => $this->name,
            'database_uuid' => $this->database->uuid,
            'error' => $this->errorMessage,
        ];
    }
}
