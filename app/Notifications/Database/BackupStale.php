<?php

namespace App\Notifications\Database;

use App\Models\ScheduledDatabaseBackup;
use App\Notifications\CustomEmailNotification;
use App\Notifications\Dto\DiscordMessage;
use App\Notifications\Dto\PushoverMessage;
use App\Notifications\Dto\SlackMessage;
use Carbon\Carbon;
use Illuminate\Notifications\Messages\MailMessage;

class BackupStale extends CustomEmailNotification
{
    public string $databaseName;

    public string $frequency;

    public string $lastSuccessAt;

    public int $staleHours;

    public function __construct(
        ScheduledDatabaseBackup $backup,
        public $database,
        Carbon $lastSuccessAt,
        int $staleHours
    ) {
        $this->onQueue('high');
        $this->databaseName = $database->name;
        $this->frequency = $backup->frequency;
        $this->lastSuccessAt = $lastSuccessAt->diffForHumans();
        $this->staleHours = $staleHours;
    }

    public function via(object $notifiable): array
    {
        return $notifiable->getEnabledChannels('backup_failure');
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = new MailMessage;
        $mail->subject("Saturn Platform: [ACTION REQUIRED] Database Backup STALE for {$this->databaseName}");
        $mail->view('emails.backup-stale', [
            'database_name' => $this->databaseName,
            'frequency' => $this->frequency,
            'last_success_at' => $this->lastSuccessAt,
            'stale_hours' => $this->staleHours,
        ]);

        return $mail;
    }

    public function toDiscord(): DiscordMessage
    {
        $message = new DiscordMessage(
            title: ':warning: Database backup is stale',
            description: "Database backup for **{$this->databaseName}** has not succeeded in over {$this->staleHours} hours.",
            color: DiscordMessage::warningColor(),
            isCritical: true,
        );

        $message->addField('Last Successful Backup', $this->lastSuccessAt, true);
        $message->addField('Schedule Frequency', $this->frequency, true);

        return $message;
    }

    public function toTelegram(): array
    {
        $message = "⚠️ Saturn Platform: Database backup for {$this->databaseName} has not succeeded in over {$this->staleHours} hours.\n\n"
            ."Last successful backup: {$this->lastSuccessAt}\n"
            ."Schedule: {$this->frequency}";

        return ['message' => $message];
    }

    public function toPushover(): PushoverMessage
    {
        return new PushoverMessage(
            title: 'Database backup stale',
            level: 'warning',
            message: "Database backup for {$this->databaseName} has not succeeded in over {$this->staleHours} hours.<br/><b>Last success:</b> {$this->lastSuccessAt}",
        );
    }

    public function toSlack(): SlackMessage
    {
        return new SlackMessage(
            title: 'Database backup is stale',
            description: "Database backup for *{$this->databaseName}* has not succeeded in over {$this->staleHours} hours.\n\n*Last successful backup:* {$this->lastSuccessAt}\n*Schedule:* {$this->frequency}",
            color: SlackMessage::warningColor()
        );
    }

    public function toWebhook(): array
    {
        return [
            'success' => false,
            'message' => 'Database backup is stale',
            'event' => 'backup_stale',
            'database_name' => $this->databaseName,
            'database_uuid' => $this->database->uuid,
            'frequency' => $this->frequency,
            'last_success_at' => $this->lastSuccessAt,
            'stale_hours' => $this->staleHours,
        ];
    }
}
