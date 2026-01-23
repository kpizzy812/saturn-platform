<?php

namespace App\Notifications\Server;

use App\Models\Server;
use App\Notifications\CustomEmailNotification;
use App\Notifications\Dto\DiscordMessage;
use App\Notifications\Dto\PushoverMessage;
use App\Notifications\Dto\SlackMessage;
use Illuminate\Notifications\Messages\MailMessage;

class ServerAutoProvisioned extends CustomEmailNotification
{
    public function __construct(
        public Server $newServer,
        public Server $triggerServer,
        public string $triggerReason,
        public array $triggerMetrics = []
    ) {
        $this->onQueue('high');
    }

    public function via(object $notifiable): array
    {
        return $notifiable->getEnabledChannels('server_disk_usage');
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = new MailMessage;
        $mail->subject('Saturn Platform: New server auto-provisioned!');
        $mail->view('emails.server-auto-provisioned', [
            'new_server_name' => $this->newServer->name,
            'new_server_ip' => $this->newServer->ip,
            'trigger_server_name' => $this->triggerServer->name,
            'trigger_reason' => $this->getTriggerReasonLabel(),
            'trigger_metrics' => $this->triggerMetrics,
        ]);

        return $mail;
    }

    public function toDiscord(): DiscordMessage
    {
        $message = new DiscordMessage(
            title: ':rocket: New server auto-provisioned',
            description: 'A new server has been automatically created due to resource constraints.',
            color: DiscordMessage::infoColor(),
            isCritical: false,
        );

        $message->addField('New Server', $this->newServer->name, true);
        $message->addField('IP Address', $this->newServer->ip, true);
        $message->addField('Trigger', $this->getTriggerReasonLabel(), true);
        $message->addField('Triggered By', $this->triggerServer->name, true);

        if (! empty($this->triggerMetrics)) {
            $metricsText = [];
            if (isset($this->triggerMetrics['cpu'])) {
                $metricsText[] = 'CPU: '.round($this->triggerMetrics['cpu'], 1).'%';
            }
            if (isset($this->triggerMetrics['memory'])) {
                $metricsText[] = 'Memory: '.round($this->triggerMetrics['memory'], 1).'%';
            }
            $message->addField('Metrics at Trigger', implode(', ', $metricsText));
        }

        $message->addField('View Server', '[Open]('.base_url().'/server/'.$this->newServer->uuid.')');

        return $message;
    }

    public function toTelegram(): array
    {
        $metricsInfo = '';
        if (! empty($this->triggerMetrics)) {
            $metricsInfo = ' (';
            if (isset($this->triggerMetrics['cpu'])) {
                $metricsInfo .= 'CPU: '.round($this->triggerMetrics['cpu'], 1).'%';
            }
            if (isset($this->triggerMetrics['memory'])) {
                $metricsInfo .= ', Memory: '.round($this->triggerMetrics['memory'], 1).'%';
            }
            $metricsInfo .= ')';
        }

        return [
            'message' => "Saturn Platform: New server auto-provisioned!\n\n".
                "New Server: {$this->newServer->name}\n".
                "IP: {$this->newServer->ip}\n".
                "Trigger: {$this->getTriggerReasonLabel()}\n".
                "Triggered by: {$this->triggerServer->name}{$metricsInfo}",
        ];
    }

    public function toPushover(): PushoverMessage
    {
        return new PushoverMessage(
            title: 'New server auto-provisioned',
            level: 'info',
            message: "A new server <b>{$this->newServer->name}</b> ({$this->newServer->ip}) has been created.<br/><br/>".
                "<b>Triggered by:</b> {$this->triggerServer->name}<br/>".
                "<b>Reason:</b> {$this->getTriggerReasonLabel()}",
            buttons: [
                'View server' => base_url().'/server/'.$this->newServer->uuid,
            ],
        );
    }

    public function toSlack(): SlackMessage
    {
        $description = "A new server has been auto-provisioned!\n\n";
        $description .= "New Server: {$this->newServer->name}\n";
        $description .= "IP Address: {$this->newServer->ip}\n";
        $description .= "Triggered by: {$this->triggerServer->name}\n";
        $description .= "Reason: {$this->getTriggerReasonLabel()}\n\n";
        $description .= 'View server: '.base_url().'/server/'.$this->newServer->uuid;

        return new SlackMessage(
            title: 'New server auto-provisioned',
            description: $description,
            color: SlackMessage::infoColor()
        );
    }

    public function toWebhook(): array
    {
        return [
            'success' => true,
            'message' => 'New server auto-provisioned',
            'event' => 'server_auto_provisioned',
            'new_server_name' => $this->newServer->name,
            'new_server_uuid' => $this->newServer->uuid,
            'new_server_ip' => $this->newServer->ip,
            'trigger_server_name' => $this->triggerServer->name,
            'trigger_server_uuid' => $this->triggerServer->uuid,
            'trigger_reason' => $this->triggerReason,
            'trigger_metrics' => $this->triggerMetrics,
            'url' => base_url().'/server/'.$this->newServer->uuid,
        ];
    }

    private function getTriggerReasonLabel(): string
    {
        return match ($this->triggerReason) {
            'cpu_critical' => 'CPU Overload',
            'memory_critical' => 'Memory Overload',
            'manual' => 'Manual Request',
            default => $this->triggerReason,
        };
    }
}
