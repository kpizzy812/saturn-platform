<?php

namespace App\Notifications\Application;

use App\Models\Application;
use App\Models\ApplicationDeploymentQueue;
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

    public string $project_uuid;

    public string $environment_uuid;

    public string $environment_name;

    public ?string $deployment_url = null;

    public ?string $approval_url = null;

    public function __construct(Application $application, ApplicationDeploymentQueue $deployment)
    {
        $this->onQueue('high');
        $this->application = $application;
        $this->deployment = $deployment;
        $this->deployment_uuid = $deployment->deployment_uuid;
        $this->application_name = data_get($application, 'name');
        $this->project_uuid = data_get($application, 'environment.project.uuid');
        $this->environment_uuid = data_get($application, 'environment.uuid');
        $this->environment_name = data_get($application, 'environment.name');
        $this->deployment_url = base_url()."/project/{$this->project_uuid}/environment/{$this->environment_uuid}/application/{$this->application->uuid}/deployment/{$this->deployment_uuid}";
        $this->approval_url = base_url().'/admin/deployments/approvals';
    }

    public function via(object $notifiable): array
    {
        return $notifiable->getEnabledChannels('deployment_success');
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = new MailMessage;
        $mail->subject("Saturn Platform: Deployment approval required for {$this->application_name}");
        $mail->view('emails.application-deployment-approval-required', [
            'name' => $this->application_name,
            'environment' => $this->environment_name,
            'deployment_url' => $this->deployment_url,
            'approval_url' => $this->approval_url,
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

        $message->addField('Project', data_get($this->application, 'environment.project.name'), true);
        $message->addField('Environment', $this->environment_name, true);
        $message->addField('Name', $this->application_name, true);
        $message->addField('Deployment logs', '[Link]('.$this->deployment_url.')');
        $message->addField('Approve/Reject', '[Admin Panel]('.$this->approval_url.')');

        return $message;
    }

    public function toTelegram(): array
    {
        $message = '⏳ Deployment approval required for '.$this->application_name;
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

        return [
            'message' => $message,
            'buttons' => $buttons,
        ];
    }

    public function toPushover(): PushoverMessage
    {
        $title = 'Deployment approval required';
        $message = "Deployment approval required for {$this->application_name}";
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
        $description = "Deployment approval required for {$this->application_name}";
        $description .= "\n\n*Project:* ".data_get($this->application, 'environment.project.name');
        $description .= "\n*Environment:* {$this->environment_name}";
        $description .= "\n*<{$this->deployment_url}|Deployment Logs>*";
        $description .= "\n*<{$this->approval_url}|Approve/Reject>*";

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
            'deployment_uuid' => $this->deployment_uuid,
            'deployment_url' => $this->deployment_url,
            'approval_url' => $this->approval_url,
            'project' => data_get($this->application, 'environment.project.name'),
            'environment' => $this->environment_name,
        ];
    }
}
