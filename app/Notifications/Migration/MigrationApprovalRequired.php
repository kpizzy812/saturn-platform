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
 * Notification sent to approvers when a migration requires approval.
 */
class MigrationApprovalRequired extends Notification implements ShouldQueue
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
        $requester = $this->migration->requestedBy?->name ?? 'Unknown User';
        $direction = $this->migration->migrationDirection;
        $project = $this->migration->sourceEnvironment->project?->name ?? 'Unknown Project';

        $approvalUrl = base_url().'/approvals';

        return (new MailMessage)
            ->subject("Saturn Platform: Migration Approval Required - {$resourceName}")
            ->greeting('Migration Approval Required')
            ->line('A migration request requires your approval.')
            ->line("**Resource:** {$resourceName} ({$resourceType})")
            ->line("**Project:** {$project}")
            ->line("**Migration:** {$direction}")
            ->line("**Requested by:** {$requester}")
            ->action('Review Request', $approvalUrl)
            ->line('Please review and approve or reject this migration request.');
    }

    public function toDiscord(): DiscordMessage
    {
        $source = $this->migration->source;
        $resourceName = $source?->name ?? 'Unknown Resource';
        $resourceType = $this->migration->sourceTypeName;
        $requester = $this->migration->requestedBy?->name ?? 'Unknown User';
        $direction = $this->migration->migrationDirection;
        $project = $this->migration->sourceEnvironment->project?->name ?? 'Unknown Project';

        $message = new DiscordMessage(
            title: ':warning: Migration Approval Required',
            description: "A migration request requires your approval.\n\n**Resource:** {$resourceName} ({$resourceType})\n**Project:** {$project}\n**Migration:** {$direction}\n**Requested by:** {$requester}",
            color: DiscordMessage::warningColor(),
            isCritical: true,
        );

        $message->addField(name: 'Review', value: '[Approve/Reject]('.base_url().'/approvals)', inline: true);

        return $message;
    }

    public function toTelegram(): array
    {
        $source = $this->migration->source;
        $resourceName = $source?->name ?? 'Unknown Resource';
        $resourceType = $this->migration->sourceTypeName;
        $requester = $this->migration->requestedBy?->name ?? 'Unknown User';
        $direction = $this->migration->migrationDirection;
        $project = $this->migration->sourceEnvironment->project?->name ?? 'Unknown Project';

        return [
            'message' => "Saturn Platform: Migration Approval Required\n\nResource: {$resourceName} ({$resourceType})\nProject: {$project}\nMigration: {$direction}\nRequested by: {$requester}",
            'buttons' => [
                [
                    'text' => 'Review Request',
                    'url' => base_url().'/approvals',
                ],
            ],
        ];
    }

    public function toSlack(): SlackMessage
    {
        $source = $this->migration->source;
        $resourceName = $source?->name ?? 'Unknown Resource';
        $resourceType = $this->migration->sourceTypeName;
        $requester = $this->migration->requestedBy?->name ?? 'Unknown User';
        $direction = $this->migration->migrationDirection;
        $project = $this->migration->sourceEnvironment->project?->name ?? 'Unknown Project';

        return new SlackMessage(
            title: 'Migration Approval Required',
            description: "A migration request requires your approval.\n\n*Resource:* {$resourceName} ({$resourceType})\n*Project:* {$project}\n*Migration:* {$direction}\n*Requested by:* {$requester}\n\n<".base_url().'/approvals|Review Request>'
        );
    }
}
