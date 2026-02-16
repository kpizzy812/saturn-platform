<?php

namespace App\Notifications\Migration;

use App\Models\EnvironmentMigration;
use App\Notifications\Channels\EmailChannel;
use App\Notifications\Dto\DiscordMessage;
use App\Notifications\Dto\SlackMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Queue\Middleware\RateLimited;

/**
 * Notification sent when migration fails.
 */
class MigrationFailed extends Notification implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    public function __construct(
        protected EnvironmentMigration $migration,
        protected string $error = 'Unknown error'
    ) {
        $this->onQueue('high');
    }

    public function via(object $notifiable): array
    {
        return $notifiable->getEnabledChannels('migration') ?? [EmailChannel::class];
    }

    public function middleware(object $notifiable, string $channel): array
    {
        return match ($channel) {
            EmailChannel::class => [new RateLimited('email')],
            default => [],
        };
    }

    public function toMail(object $notifiable): MailMessage
    {
        $source = $this->migration->source;
        $resourceName = $source->name ?? 'Unknown Resource';
        $resourceType = $this->migration->sourceTypeName;
        $direction = $this->migration->migrationDirection;
        $project = $this->migration->sourceEnvironment->project->name ?? 'Unknown Project';

        return (new MailMessage)
            ->subject("Saturn Platform: Migration Failed - {$resourceName}")
            ->greeting('Migration Failed')
            ->line('Your migration request has failed.')
            ->line("**Resource:** {$resourceName} ({$resourceType})")
            ->line("**Project:** {$project}")
            ->line("**Migration:** {$direction}")
            ->line("**Error:** {$this->error}")
            ->action('View Projects', base_url().'/projects')
            ->line('Please check the migration logs for more details.');
    }

    public function toDiscord(): DiscordMessage
    {
        $source = $this->migration->source;
        $resourceName = $source->name ?? 'Unknown Resource';
        $resourceType = $this->migration->sourceTypeName;
        $direction = $this->migration->migrationDirection;
        $project = $this->migration->sourceEnvironment->project->name ?? 'Unknown Project';

        // Truncate error message for Discord
        $errorTruncated = strlen($this->error) > 200
            ? substr($this->error, 0, 200).'...'
            : $this->error;

        $message = new DiscordMessage(
            title: ':x: Migration Failed',
            description: "Your migration request has failed.\n\n**Resource:** {$resourceName} ({$resourceType})\n**Project:** {$project}\n**Migration:** {$direction}\n**Error:** {$errorTruncated}",
            color: DiscordMessage::errorColor(),
            isCritical: true,
        );

        return $message;
    }

    public function toTelegram(): array
    {
        $source = $this->migration->source;
        $resourceName = $source->name ?? 'Unknown Resource';
        $resourceType = $this->migration->sourceTypeName;
        $direction = $this->migration->migrationDirection;
        $project = $this->migration->sourceEnvironment->project->name ?? 'Unknown Project';

        // Truncate error message for Telegram
        $errorTruncated = strlen($this->error) > 200
            ? substr($this->error, 0, 200).'...'
            : $this->error;

        return [
            'message' => "Saturn Platform: Migration Failed\n\nResource: {$resourceName} ({$resourceType})\nProject: {$project}\nMigration: {$direction}\nError: {$errorTruncated}",
            'buttons' => [
                [
                    'text' => 'View Projects',
                    'url' => base_url().'/projects',
                ],
            ],
        ];
    }

    public function toSlack(): SlackMessage
    {
        $source = $this->migration->source;
        $resourceName = $source->name ?? 'Unknown Resource';
        $resourceType = $this->migration->sourceTypeName;
        $direction = $this->migration->migrationDirection;
        $project = $this->migration->sourceEnvironment->project->name ?? 'Unknown Project';

        // Truncate error message for Slack
        $errorTruncated = strlen($this->error) > 200
            ? substr($this->error, 0, 200).'...'
            : $this->error;

        return new SlackMessage(
            title: 'Migration Failed',
            description: "Your migration request has failed.\n\n*Resource:* {$resourceName} ({$resourceType})\n*Project:* {$project}\n*Migration:* {$direction}\n*Error:* {$errorTruncated}"
        );
    }
}
