<?php

namespace App\Notifications\Database;

use App\Notifications\CustomEmailNotification;
use App\Notifications\Dto\DiscordMessage;
use App\Notifications\Dto\PushoverMessage;
use App\Notifications\Dto\SlackMessage;
use Illuminate\Notifications\Messages\MailMessage;

class BackupRestoreTestSuccess extends CustomEmailNotification
{
    public string $name;

    public function __construct(public $database, public int $duration)
    {
        $this->onQueue('high');
        $this->name = $database->name;
    }

    public function via(object $notifiable): array
    {
        return $notifiable->getEnabledChannels('backup_success');
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = new MailMessage;
        $mail->subject("Saturn Platform: Backup restore test passed for {$this->name}");
        $mail->line("The automated restore test for database **{$this->name}** completed successfully.")
            ->line("**Duration:** {$this->duration} seconds")
            ->line('This confirms that your backup can be successfully restored.');

        return $mail;
    }

    public function toDiscord(): DiscordMessage
    {
        $message = new DiscordMessage(
            title: ':white_check_mark: Backup Restore Test Passed',
            description: "Automated restore test for **{$this->name}** completed successfully.",
            color: DiscordMessage::successColor(),
        );

        $message->addField('Duration', "{$this->duration}s", true);

        return $message;
    }

    public function toTelegram(): array
    {
        return [
            'message' => "Saturn Platform: Backup restore test for {$this->name} passed in {$this->duration} seconds.",
        ];
    }

    public function toPushover(): PushoverMessage
    {
        return new PushoverMessage(
            title: 'Backup Restore Test Passed',
            level: 'success',
            message: "Restore test for {$this->name} completed in {$this->duration}s.",
        );
    }

    public function toSlack(): SlackMessage
    {
        return new SlackMessage(
            title: 'Backup Restore Test Passed',
            description: "Automated restore test for *{$this->name}* completed successfully in {$this->duration} seconds.",
            color: SlackMessage::successColor()
        );
    }

    public function toWebhook(): array
    {
        return [
            'success' => true,
            'message' => 'Backup restore test passed',
            'event' => 'backup_restore_test_success',
            'database_name' => $this->name,
            'database_uuid' => $this->database->uuid,
            'duration_seconds' => $this->duration,
        ];
    }
}
