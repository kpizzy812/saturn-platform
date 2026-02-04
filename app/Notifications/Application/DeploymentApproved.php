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

class DeploymentApproved extends CustomEmailNotification
{
    public Application $application;

    public ApplicationDeploymentQueue $deployment;

    public User $approvedBy;

    public string $deployment_uuid;

    public string $application_name;

    public string $project_uuid;

    public string $environment_uuid;

    public string $environment_name;

    public ?string $deployment_url = null;

    public ?string $note = null;

    public function __construct(Application $application, ApplicationDeploymentQueue $deployment, User $approvedBy)
    {
        $this->onQueue('high');
        $this->application = $application;
        $this->deployment = $deployment;
        $this->approvedBy = $approvedBy;
        $this->deployment_uuid = $deployment->deployment_uuid;
        $this->application_name = data_get($application, 'name');
        $this->project_uuid = data_get($application, 'environment.project.uuid');
        $this->environment_uuid = data_get($application, 'environment.uuid');
        $this->environment_name = data_get($application, 'environment.name');
        $this->note = $deployment->approval_note;
        $this->deployment_url = base_url()."/deployments/{$this->deployment_uuid}";
    }

    public function via(object $notifiable): array
    {
        return $notifiable->getEnabledChannels('deployment_success');
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = new MailMessage;
        $mail->subject("Saturn Platform: Deployment approved for {$this->application_name}");
        $mail->view('emails.application-deployment-approved', [
            'name' => $this->application_name,
            'environment' => $this->environment_name,
            'deployment_url' => $this->deployment_url,
            'approved_by' => $this->approvedBy->name,
            'note' => $this->note,
        ]);

        return $mail;
    }

    public function toDiscord(): DiscordMessage
    {
        $message = new DiscordMessage(
            title: ':white_check_mark: Deployment approved',
            description: 'Your deployment has been approved and is proceeding',
            color: DiscordMessage::successColor(),
        );

        $message->addField('Project', data_get($this->application, 'environment.project.name'), true);
        $message->addField('Environment', $this->environment_name, true);
        $message->addField('Name', $this->application_name, true);
        $message->addField('Approved by', $this->approvedBy->name, true);
        if ($this->note) {
            $message->addField('Note', $this->note);
        }
        $message->addField('Deployment logs', '[Link]('.$this->deployment_url.')');

        return $message;
    }

    public function toTelegram(): array
    {
        $message = '✅ Deployment approved for '.$this->application_name.' by '.$this->approvedBy->name;
        if ($this->note) {
            $message .= "\nNote: {$this->note}";
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
        $title = 'Deployment approved';
        $message = "Deployment approved for {$this->application_name} by {$this->approvedBy->name}";
        if ($this->note) {
            $message .= "\nNote: {$this->note}";
        }
        $buttons = [
            [
                'text' => 'View Deployment',
                'url' => $this->deployment_url,
            ],
        ];

        return new PushoverMessage(
            title: $title,
            level: 'success',
            message: $message,
            buttons: $buttons,
        );
    }

    public function toSlack(): SlackMessage
    {
        $title = 'Deployment approved';
        $description = "Deployment approved for {$this->application_name}";
        $description .= "\n\n*Project:* ".data_get($this->application, 'environment.project.name');
        $description .= "\n*Environment:* {$this->environment_name}";
        $description .= "\n*Approved by:* {$this->approvedBy->name}";
        if ($this->note) {
            $description .= "\n*Note:* {$this->note}";
        }
        $description .= "\n*<{$this->deployment_url}|Deployment Logs>*";

        return new SlackMessage(
            title: $title,
            description: $description,
            color: SlackMessage::successColor()
        );
    }

    public function toWebhook(): array
    {
        $data = [
            'success' => true,
            'message' => 'Deployment approved',
            'event' => 'deployment_approved',
            'application_name' => $this->application_name,
            'application_uuid' => $this->application->uuid,
            'deployment_uuid' => $this->deployment_uuid,
            'deployment_url' => $this->deployment_url,
            'project' => data_get($this->application, 'environment.project.name'),
            'environment' => $this->environment_name,
            'approved_by' => $this->approvedBy->name,
            'approved_by_email' => $this->approvedBy->email,
        ];

        if ($this->note) {
            $data['note'] = $this->note;
        }

        return $data;
    }
}
