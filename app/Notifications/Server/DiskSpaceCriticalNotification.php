<?php

namespace App\Notifications\Server;

use App\Models\Server;
use App\Notifications\CustomEmailNotification;
use App\Notifications\Dto\DiscordMessage;
use App\Notifications\Dto\PushoverMessage;
use App\Notifications\Dto\SlackMessage;
use Illuminate\Notifications\Messages\MailMessage;

class DiskSpaceCriticalNotification extends CustomEmailNotification
{
    public const CRITICAL_THRESHOLD = 95;

    public function __construct(public Server $server, public int $disk_usage)
    {
        $this->onQueue('high');
    }

    public function via(object $notifiable): array
    {
        return $notifiable->getEnabledChannels('server_disk_usage');
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = new MailMessage;
        $mail->subject("Saturn Platform: Server ({$this->server->name}) CRITICAL disk usage — deployments blocked!");
        $mail->view('emails.disk-space-critical', [
            'name' => $this->server->name,
            'disk_usage' => $this->disk_usage,
            'threshold' => self::CRITICAL_THRESHOLD,
        ]);

        return $mail;
    }

    public function toDiscord(): DiscordMessage
    {
        $message = new DiscordMessage(
            title: ':no_entry: CRITICAL disk usage — deployments blocked',
            description: "Server '{$this->server->name}' disk usage is critically high. New deployments are blocked.",
            color: DiscordMessage::errorColor(),
            isCritical: true,
        );

        $message->addField('Disk usage', "{$this->disk_usage}%", true);
        $message->addField('Critical threshold', self::CRITICAL_THRESHOLD.'%', true);
        $message->addField('Action required', 'Free disk space to resume deployments', true);
        $message->addField('Change Settings', '[Server]('.base_url().'/server/'.$this->server->uuid.'#advanced)');

        return $message;
    }

    public function toTelegram(): array
    {
        return [
            'message' => "🚨 Saturn Platform: Server '{$this->server->name}' CRITICAL disk usage!\nDisk usage: {$this->disk_usage}%. Threshold: ".self::CRITICAL_THRESHOLD."%.\nNew deployments are blocked. Free disk space immediately to resume deployments.",
        ];
    }

    public function toPushover(): PushoverMessage
    {
        return new PushoverMessage(
            title: 'CRITICAL disk usage — deployments blocked',
            level: 'error',
            message: "Server '{$this->server->name}' CRITICAL disk usage!<br/><br/><b>Disk usage:</b> {$this->disk_usage}%.<br/><b>Threshold:</b> ".self::CRITICAL_THRESHOLD.'%.<br/>New deployments are blocked. Free disk space immediately.',
            buttons: [
                'Manage server' => base_url().'/server/'.$this->server->uuid.'#advanced',
            ],
        );
    }

    public function toSlack(): SlackMessage
    {
        $description = "Server '{$this->server->name}' CRITICAL disk usage — deployments blocked!\n";
        $description .= "Disk usage: {$this->disk_usage}%\n";
        $description .= 'Critical threshold: '.self::CRITICAL_THRESHOLD."%\n\n";
        $description .= "New deployments are blocked. Free disk space immediately.\n";
        $description .= 'Manage server: '.base_url().'/server/'.$this->server->uuid.'#advanced';

        return new SlackMessage(
            title: 'CRITICAL disk usage — deployments blocked',
            description: $description,
            color: SlackMessage::errorColor()
        );
    }

    public function toWebhook(): array
    {
        return [
            'success' => false,
            'message' => 'Critical disk usage — deployments blocked',
            'event' => 'disk_space_critical',
            'server_name' => $this->server->name,
            'server_uuid' => $this->server->uuid,
            'disk_usage' => $this->disk_usage,
            'threshold' => self::CRITICAL_THRESHOLD,
            'deployments_blocked' => true,
            'url' => base_url().'/server/'.$this->server->uuid,
        ];
    }
}
