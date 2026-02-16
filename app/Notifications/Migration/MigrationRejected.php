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
 * Notification sent to requester when migration is rejected.
 */
class MigrationRejected extends Notification implements ShouldQueue
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
        $rejector = $this->migration->approvedBy->name ?? 'Unknown User';
        $direction = $this->migration->migrationDirection;
        $project = $this->migration->sourceEnvironment->project->name ?? 'Unknown Project';
        $reason = $this->migration->rejection_reason ?? 'No reason provided';

        return (new MailMessage)
            ->subject("Saturn Platform: Migration Rejected - {$resourceName}")
            ->greeting('Migration Rejected')
            ->line('Your migration request has been rejected.')
            ->line("**Resource:** {$resourceName} ({$resourceType})")
            ->line("**Project:** {$project}")
            ->line("**Migration:** {$direction}")
            ->line("**Rejected by:** {$rejector}")
            ->line("**Reason:** {$reason}")
            ->action('View Projects', base_url().'/projects')
            ->line('Please contact the approver for more information.');
    }

    public function toDiscord(): DiscordMessage
    {
        $source = $this->migration->source;
        $resourceName = $source->name ?? 'Unknown Resource';
        $resourceType = $this->migration->sourceTypeName;
        $rejector = $this->migration->approvedBy->name ?? 'Unknown User';
        $direction = $this->migration->migrationDirection;
        $project = $this->migration->sourceEnvironment->project->name ?? 'Unknown Project';
        $reason = $this->migration->rejection_reason ?? 'No reason provided';

        $message = new DiscordMessage(
            title: ':x: Migration Rejected',
            description: "Your migration request has been rejected.\n\n**Resource:** {$resourceName} ({$resourceType})\n**Project:** {$project}\n**Migration:** {$direction}\n**Rejected by:** {$rejector}\n**Reason:** {$reason}",
            color: DiscordMessage::errorColor(),
        );

        return $message;
    }

    public function toTelegram(): array
    {
        $source = $this->migration->source;
        $resourceName = $source->name ?? 'Unknown Resource';
        $resourceType = $this->migration->sourceTypeName;
        $rejector = $this->migration->approvedBy->name ?? 'Unknown User';
        $direction = $this->migration->migrationDirection;
        $project = $this->migration->sourceEnvironment->project->name ?? 'Unknown Project';
        $reason = $this->migration->rejection_reason ?? 'No reason provided';

        return [
            'message' => "Saturn Platform: Migration Rejected\n\nResource: {$resourceName} ({$resourceType})\nProject: {$project}\nMigration: {$direction}\nRejected by: {$rejector}\nReason: {$reason}",
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
        $rejector = $this->migration->approvedBy->name ?? 'Unknown User';
        $direction = $this->migration->migrationDirection;
        $project = $this->migration->sourceEnvironment->project->name ?? 'Unknown Project';
        $reason = $this->migration->rejection_reason ?? 'No reason provided';

        return new SlackMessage(
            title: 'Migration Rejected',
            description: "Your migration request has been rejected.\n\n*Resource:* {$resourceName} ({$resourceType})\n*Project:* {$project}\n*Migration:* {$direction}\n*Rejected by:* {$rejector}\n*Reason:* {$reason}"
        );
    }
}
