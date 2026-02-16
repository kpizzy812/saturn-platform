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
 * Notification sent when migration is completed successfully.
 */
class MigrationCompleted extends Notification implements ShouldQueue
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
        $target = $this->migration->target;
        $resourceName = $source->name ?? 'Unknown Resource';
        $resourceType = $this->migration->sourceTypeName;
        $direction = $this->migration->migrationDirection;
        $project = $this->migration->sourceEnvironment->project->name ?? 'Unknown Project';
        $targetEnv = $this->migration->targetEnvironment->name ?? 'Unknown';

        return (new MailMessage)
            ->subject("Saturn Platform: Migration Completed - {$resourceName}")
            ->greeting('Migration Completed Successfully')
            ->line('Your resource has been successfully migrated.')
            ->line("**Resource:** {$resourceName} ({$resourceType})")
            ->line("**Project:** {$project}")
            ->line("**Migration:** {$direction}")
            ->line("**Target Environment:** {$targetEnv}")
            ->action('View Resource', base_url().'/projects')
            ->line('The resource is now available in the target environment.');
    }

    public function toDiscord(): DiscordMessage
    {
        $source = $this->migration->source;
        $resourceName = $source->name ?? 'Unknown Resource';
        $resourceType = $this->migration->sourceTypeName;
        $direction = $this->migration->migrationDirection;
        $project = $this->migration->sourceEnvironment->project->name ?? 'Unknown Project';
        $targetEnv = $this->migration->targetEnvironment->name ?? 'Unknown';

        $message = new DiscordMessage(
            title: ':rocket: Migration Completed',
            description: "Your resource has been successfully migrated.\n\n**Resource:** {$resourceName} ({$resourceType})\n**Project:** {$project}\n**Migration:** {$direction}\n**Target Environment:** {$targetEnv}",
            color: DiscordMessage::successColor(),
        );

        $message->addField(name: 'Dashboard', value: '[View Resource]('.base_url().'/projects)', inline: true);

        return $message;
    }

    public function toTelegram(): array
    {
        $source = $this->migration->source;
        $resourceName = $source->name ?? 'Unknown Resource';
        $resourceType = $this->migration->sourceTypeName;
        $direction = $this->migration->migrationDirection;
        $project = $this->migration->sourceEnvironment->project->name ?? 'Unknown Project';
        $targetEnv = $this->migration->targetEnvironment->name ?? 'Unknown';

        return [
            'message' => "Saturn Platform: Migration Completed\n\nResource: {$resourceName} ({$resourceType})\nProject: {$project}\nMigration: {$direction}\nTarget Environment: {$targetEnv}",
            'buttons' => [
                [
                    'text' => 'View Resource',
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
        $targetEnv = $this->migration->targetEnvironment->name ?? 'Unknown';

        return new SlackMessage(
            title: 'Migration Completed',
            description: "Your resource has been successfully migrated.\n\n*Resource:* {$resourceName} ({$resourceType})\n*Project:* {$project}\n*Migration:* {$direction}\n*Target Environment:* {$targetEnv}"
        );
    }
}
