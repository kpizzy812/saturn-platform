<?php

namespace App\Notifications\Application;

use App\Models\Application;
use App\Models\ApplicationDeploymentQueue;
use App\Models\User;
use App\Notifications\CustomEmailNotification;
use App\Notifications\Dto\DiscordMessage;
use App\Notifications\Dto\PushoverMessage;
use App\Notifications\Dto\SlackMessage;
use Illuminate\Notifications\Messages\MailMessage;

class DeploymentRejected extends CustomEmailNotification
{
    public Application $application;

    public ApplicationDeploymentQueue $deployment;

    public User $rejectedBy;

    public string $deployment_uuid;

    public string $application_name;

    public string $project_uuid;

    public string $environment_uuid;

    public string $environment_name;

    public ?string $deployment_url = null;

    public ?string $note = null;

    public function __construct(Application $application, ApplicationDeploymentQueue $deployment, User $rejectedBy)
    {
        $this->onQueue('high');
        $this->application = $application;
        $this->deployment = $deployment;
        $this->rejectedBy = $rejectedBy;
        $this->deployment_uuid = $deployment->deployment_uuid;
        $this->application_name = data_get($application, 'name');
        $this->project_uuid = data_get($application, 'environment.project.uuid');
        $this->environment_uuid = data_get($application, 'environment.uuid');
        $this->environment_name = data_get($application, 'environment.name');
        $this->note = $deployment->approval_note;
        $this->deployment_url = base_url()."/project/{$this->project_uuid}/environment/{$this->environment_uuid}/application/{$this->application->uuid}/deployment/{$this->deployment_uuid}";
    }

    public function via(object $notifiable): array
    {
        return $notifiable->getEnabledChannels('deployment_failed');
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = new MailMessage;
        $mail->subject("Saturn Platform: Deployment rejected for {$this->application_name}");
        $mail->view('emails.application-deployment-rejected', [
            'name' => $this->application_name,
            'environment' => $this->environment_name,
            'deployment_url' => $this->deployment_url,
            'rejected_by' => $this->rejectedBy->name,
            'note' => $this->note,
        ]);

        return $mail;
    }

    public function toDiscord(): DiscordMessage
    {
        $message = new DiscordMessage(
            title: ':x: Deployment rejected',
            description: 'Your deployment has been rejected',
            color: DiscordMessage::errorColor(),
        );

        $message->addField('Project', data_get($this->application, 'environment.project.name'), true);
        $message->addField('Environment', $this->environment_name, true);
        $message->addField('Name', $this->application_name, true);
        $message->addField('Rejected by', $this->rejectedBy->name, true);
        if ($this->note) {
            $message->addField('Reason', $this->note);
        }
        $message->addField('Deployment logs', '[Link]('.$this->deployment_url.')');

        return $message;
    }

    public function toTelegram(): array
    {
        $message = '❌ Deployment rejected for '.$this->application_name.' by '.$this->rejectedBy->name;
        if ($this->note) {
            $message .= "\nReason: {$this->note}";
        }
        $buttons = [
            [
                'text' => 'View Deployment',
                'url' => $this->deployment_url,
            ],
        ];

        return [
            'message' => $message,
            'buttons' => $buttons,
        ];
    }

    public function toPushover(): PushoverMessage
    {
        $title = 'Deployment rejected';
        $message = "Deployment rejected for {$this->application_name} by {$this->rejectedBy->name}";
        if ($this->note) {
            $message .= "\nReason: {$this->note}";
        }
        $buttons = [
            [
                'text' => 'View Deployment',
                'url' => $this->deployment_url,
            ],
        ];

        return new PushoverMessage(
            title: $title,
            level: 'error',
            message: $message,
            buttons: $buttons,
        );
    }

    public function toSlack(): SlackMessage
    {
        $title = 'Deployment rejected';
        $description = "Deployment rejected for {$this->application_name}";
        $description .= "\n\n*Project:* ".data_get($this->application, 'environment.project.name');
        $description .= "\n*Environment:* {$this->environment_name}";
        $description .= "\n*Rejected by:* {$this->rejectedBy->name}";
        if ($this->note) {
            $description .= "\n*Reason:* {$this->note}";
        }
        $description .= "\n*<{$this->deployment_url}|Deployment Logs>*";

        return new SlackMessage(
            title: $title,
            description: $description,
            color: SlackMessage::errorColor()
        );
    }

    public function toWebhook(): array
    {
        $data = [
            'success' => false,
            'message' => 'Deployment rejected',
            'event' => 'deployment_rejected',
            'application_name' => $this->application_name,
            'application_uuid' => $this->application->uuid,
            'deployment_uuid' => $this->deployment_uuid,
            'deployment_url' => $this->deployment_url,
            'project' => data_get($this->application, 'environment.project.name'),
            'environment' => $this->environment_name,
            'rejected_by' => $this->rejectedBy->name,
            'rejected_by_email' => $this->rejectedBy->email,
        ];

        if ($this->note) {
            $data['reason'] = $this->note;
        }

        return $data;
    }
}
