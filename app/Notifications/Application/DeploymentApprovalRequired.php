<?php

namespace App\Notifications\Application;

use App\Models\Application;
use App\Models\ApplicationDeploymentQueue;
use App\Models\Team;
use App\Models\User;
use App\Notifications\Channels\EmailChannel;
use App\Notifications\Channels\InAppChannel;
use App\Notifications\CustomEmailNotification;
use App\Notifications\Dto\DiscordMessage;
use App\Notifications\Dto\PushoverMessage;
use App\Notifications\Dto\SlackMessage;
use Illuminate\Notifications\Messages\MailMessage;

class DeploymentApprovalRequired extends CustomEmailNotification
{
    public Application $application;

    public ApplicationDeploymentQueue $deployment;

    public string $deployment_uuid;

    public string $application_name;

    public string $project_name;

    public string $project_uuid;

    public string $environment_uuid;

    public string $environment_name;

    public ?string $deployment_url = null;

    public ?string $approval_url = null;

    public ?string $requested_by = null;

    public function __construct(Application $application, ApplicationDeploymentQueue $deployment)
    {
        $this->onQueue('high');
        $this->application = $application;
        $this->deployment = $deployment;
        $this->deployment_uuid = $deployment->deployment_uuid;
        $this->application_name = data_get($application, 'name');
        $this->project_name = data_get($application, 'environment.project.name');
        $this->project_uuid = data_get($application, 'environment.project.uuid');
        $this->environment_uuid = data_get($application, 'environment.uuid');
        $this->environment_name = data_get($application, 'environment.name');
        $this->deployment_url = base_url()."/project/{$this->project_uuid}/environment/{$this->environment_uuid}/application/{$this->application->uuid}/deployment/{$this->deployment_uuid}";
        $this->approval_url = base_url().'/approvals';

        // Get who requested the deployment
        if ($deployment->user_id) {
            $this->requested_by = User::find($deployment->user_id)?->name;
        }
    }

    public function via(object $notifiable): array
    {
        // For User: send only email (personal notification)
        if ($notifiable instanceof User) {
            return ['mail'];
        }

        // For Team: send to team channels (Discord, Telegram, etc.) but NOT email or InApp
        // Email goes to individual approvers, InApp is created directly
        if ($notifiable instanceof Team) {
            $channels = $notifiable->getEnabledChannels('deployment_approval_required');

            // Exclude email (sent to individual approvers) and InApp (created directly)
            return array_filter($channels, fn ($channel) => $channel !== EmailChannel::class && $channel !== InAppChannel::class);
        }

        return [];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = new MailMessage;
        $mail->subject("Saturn Platform: Deployment approval required for {$this->application_name}");
        $mail->view('emails.application-deployment-approval-required', [
            'name' => $this->application_name,
            'project' => $this->project_name,
            'environment' => $this->environment_name,
            'deployment_url' => $this->deployment_url,
            'approval_url' => $this->approval_url,
            'requested_by' => $this->requested_by,
        ]);

        return $mail;
    }

    public function toDiscord(): DiscordMessage
    {
        $message = new DiscordMessage(
            title: ':hourglass: Deployment approval required',
            description: 'A deployment is waiting for your approval',
            color: DiscordMessage::warningColor(),
        );

        $message->addField('Application', $this->application_name, true);
        $message->addField('Project', $this->project_name, true);
        $message->addField('Environment', $this->environment_name, true);

        if ($this->requested_by) {
            $message->addField('Requested by', $this->requested_by, true);
        }

        $message->addField('Deployment logs', '[View Deployment]('.$this->deployment_url.')');
        $message->addField('Approve/Reject', '[Open Approvals]('.$this->approval_url.')');

        return $message;
    }

    public function toTelegram(): array
    {
        $message = "⏳ *Deployment approval required*\n\n";
        $message .= "📦 *Application:* {$this->application_name}\n";
        $message .= "📁 *Project:* {$this->project_name}\n";
        $message .= "🌍 *Environment:* {$this->environment_name}\n";

        if ($this->requested_by) {
            $message .= "👤 *Requested by:* {$this->requested_by}\n";
        }

        $buttons = [
            [
                'text' => '📋 View Deployment',
                'url' => $this->deployment_url,
            ],
            [
                'text' => '✅ Approve/Reject',
                'url' => $this->approval_url,
            ],
        ];

        return [
            'message' => $message,
            'buttons' => $buttons,
        ];
    }

    public function toPushover(): PushoverMessage
    {
        $title = 'Deployment approval required';
        $message = "📦 {$this->application_name}\n";
        $message .= "📁 Project: {$this->project_name}\n";
        $message .= "🌍 Environment: {$this->environment_name}";

        if ($this->requested_by) {
            $message .= "\n👤 Requested by: {$this->requested_by}";
        }

        $buttons = [
            [
                'text' => 'View Deployment',
                'url' => $this->deployment_url,
            ],
            [
                'text' => 'Approve/Reject',
                'url' => $this->approval_url,
            ],
        ];

        return new PushoverMessage(
            title: $title,
            level: 'warning',
            message: $message,
            buttons: $buttons,
        );
    }

    public function toSlack(): SlackMessage
    {
        $title = 'Deployment approval required';
        $description = "A deployment is waiting for your approval\n\n";
        $description .= "*Application:* {$this->application_name}\n";
        $description .= "*Project:* {$this->project_name}\n";
        $description .= "*Environment:* {$this->environment_name}";

        if ($this->requested_by) {
            $description .= "\n*Requested by:* {$this->requested_by}";
        }

        $description .= "\n\n*<{$this->deployment_url}|View Deployment>*";
        $description .= " | *<{$this->approval_url}|Approve/Reject>*";

        return new SlackMessage(
            title: $title,
            description: $description,
            color: SlackMessage::warningColor()
        );
    }

    public function toWebhook(): array
    {
        return [
            'success' => true,
            'message' => 'Deployment approval required',
            'event' => 'deployment_approval_required',
            'application_name' => $this->application_name,
            'application_uuid' => $this->application->uuid,
            'project_name' => $this->project_name,
            'project_uuid' => $this->project_uuid,
            'environment_name' => $this->environment_name,
            'environment_uuid' => $this->environment_uuid,
            'deployment_uuid' => $this->deployment_uuid,
            'deployment_url' => $this->deployment_url,
            'approval_url' => $this->approval_url,
            'requested_by' => $this->requested_by,
        ];
    }
}
