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
 * Notification sent when migration is rolled back.
 */
class MigrationRolledBack extends Notification implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    public function __construct(
        protected EnvironmentMigration $migration
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
            ->subject("Saturn Platform: Migration Rolled Back - {$resourceName}")
            ->greeting('Migration Rolled Back')
            ->line('Your migration has been rolled back to the previous state.')
            ->line("**Resource:** {$resourceName} ({$resourceType})")
            ->line("**Project:** {$project}")
            ->line("**Migration:** {$direction}")
            ->action('View Projects', base_url().'/projects')
            ->line('The original configuration has been restored.');
    }

    public function toDiscord(): DiscordMessage
    {
        $source = $this->migration->source;
        $resourceName = $source->name ?? 'Unknown Resource';
        $resourceType = $this->migration->sourceTypeName;
        $direction = $this->migration->migrationDirection;
        $project = $this->migration->sourceEnvironment->project->name ?? 'Unknown Project';

        $message = new DiscordMessage(
            title: ':rewind: Migration Rolled Back',
            description: "Your migration has been rolled back.\n\n**Resource:** {$resourceName} ({$resourceType})\n**Project:** {$project}\n**Migration:** {$direction}",
            color: DiscordMessage::warningColor(),
        );

        $message->addField(name: 'Dashboard', value: '[View Projects]('.base_url().'/projects)', inline: true);

        return $message;
    }

    public function toTelegram(): array
    {
        $source = $this->migration->source;
        $resourceName = $source->name ?? 'Unknown Resource';
        $resourceType = $this->migration->sourceTypeName;
        $direction = $this->migration->migrationDirection;
        $project = $this->migration->sourceEnvironment->project->name ?? 'Unknown Project';

        return [
            'message' => "Saturn Platform: Migration Rolled Back\n\nResource: {$resourceName} ({$resourceType})\nProject: {$project}\nMigration: {$direction}\n\nThe original configuration has been restored.",
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

        return new SlackMessage(
            title: 'Migration Rolled Back',
            description: "Your migration has been rolled back.\n\n*Resource:* {$resourceName} ({$resourceType})\n*Project:* {$project}\n*Migration:* {$direction}\n\nThe original configuration has been restored."
        );
    }
}
