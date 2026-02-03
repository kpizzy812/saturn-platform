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
 * Notification sent to requester when migration is approved.
 */
class MigrationApproved extends Notification implements ShouldQueue
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
        $resourceName = $source?->name ?? 'Unknown Resource';
        $resourceType = $this->migration->sourceTypeName;
        $approver = $this->migration->approvedBy?->name ?? 'Unknown User';
        $direction = $this->migration->migrationDirection;
        $project = $this->migration->sourceEnvironment->project?->name ?? 'Unknown Project';

        return (new MailMessage)
            ->subject("Saturn Platform: Migration Approved - {$resourceName}")
            ->greeting('Migration Approved')
            ->line('Your migration request has been approved and is now being executed.')
            ->line("**Resource:** {$resourceName} ({$resourceType})")
            ->line("**Project:** {$project}")
            ->line("**Migration:** {$direction}")
            ->line("**Approved by:** {$approver}")
            ->action('View Migration', base_url().'/projects')
            ->line('The migration is now in progress.');
    }

    public function toDiscord(): DiscordMessage
    {
        $source = $this->migration->source;
        $resourceName = $source?->name ?? 'Unknown Resource';
        $resourceType = $this->migration->sourceTypeName;
        $approver = $this->migration->approvedBy?->name ?? 'Unknown User';
        $direction = $this->migration->migrationDirection;
        $project = $this->migration->sourceEnvironment->project?->name ?? 'Unknown Project';

        $message = new DiscordMessage(
            title: ':white_check_mark: Migration Approved',
            description: "Your migration request has been approved.\n\n**Resource:** {$resourceName} ({$resourceType})\n**Project:** {$project}\n**Migration:** {$direction}\n**Approved by:** {$approver}",
            color: DiscordMessage::successColor(),
        );

        $message->addField(name: 'Dashboard', value: '[View]('.base_url().'/projects)', inline: true);

        return $message;
    }

    public function toTelegram(): array
    {
        $source = $this->migration->source;
        $resourceName = $source?->name ?? 'Unknown Resource';
        $resourceType = $this->migration->sourceTypeName;
        $approver = $this->migration->approvedBy?->name ?? 'Unknown User';
        $direction = $this->migration->migrationDirection;
        $project = $this->migration->sourceEnvironment->project?->name ?? 'Unknown Project';

        return [
            'message' => "Saturn Platform: Migration Approved\n\nResource: {$resourceName} ({$resourceType})\nProject: {$project}\nMigration: {$direction}\nApproved by: {$approver}",
            'buttons' => [
                [
                    'text' => 'View Migration',
                    'url' => base_url().'/projects',
                ],
            ],
        ];
    }

    public function toSlack(): SlackMessage
    {
        $source = $this->migration->source;
        $resourceName = $source?->name ?? 'Unknown Resource';
        $resourceType = $this->migration->sourceTypeName;
        $approver = $this->migration->approvedBy?->name ?? 'Unknown User';
        $direction = $this->migration->migrationDirection;
        $project = $this->migration->sourceEnvironment->project?->name ?? 'Unknown Project';

        return new SlackMessage(
            title: 'Migration Approved',
            description: "Your migration request has been approved.\n\n*Resource:* {$resourceName} ({$resourceType})\n*Project:* {$project}\n*Migration:* {$direction}\n*Approved by:* {$approver}"
        );
    }
}
