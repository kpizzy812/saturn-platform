<?php

namespace App\Notifications\Server;

use App\Models\Server;
use App\Notifications\CustomEmailNotification;
use App\Notifications\Dto\DiscordMessage;
use App\Notifications\Dto\PushoverMessage;
use App\Notifications\Dto\SlackMessage;
use Illuminate\Notifications\Messages\MailMessage;

class HighMemoryUsage extends CustomEmailNotification
{
    public function __construct(
        public Server $server,
        public float $memory_usage,
        public int $threshold,
        public string $level = 'warning' // 'warning' or 'critical'
    ) {
        $this->onQueue('high');
    }

    public function via(object $notifiable): array
    {
        return $notifiable->getEnabledChannels('server_disk_usage'); // Reuse disk usage channel for now
    }

    public function toMail(object $notifiable): MailMessage
    {
        $levelText = $this->level === 'critical' ? 'Critical' : 'Warning';
        $mail = new MailMessage;
        $mail->subject("Saturn Platform: Server ({$this->server->name}) {$levelText} - High memory usage!");
        $mail->view('emails.high-memory-usage', [
            'name' => $this->server->name,
            'memory_usage' => round($this->memory_usage, 1),
            'threshold' => $this->threshold,
            'level' => $this->level,
        ]);

        return $mail;
    }

    public function toDiscord(): DiscordMessage
    {
        $emoji = $this->level === 'critical' ? ':fire:' : ':warning:';
        $color = $this->level === 'critical' ? DiscordMessage::errorColor() : DiscordMessage::warningColor();

        $message = new DiscordMessage(
            title: "{$emoji} High memory usage detected",
            description: "Server '{$this->server->name}' high memory usage detected!",
            color: $color,
            isCritical: $this->level === 'critical',
        );

        $message->addField('Memory usage', round($this->memory_usage, 1).'%', true);
        $message->addField('Threshold', "{$this->threshold}%", true);
        $message->addField('Level', ucfirst($this->level), true);
        $message->addField('Server', '[View]('.base_url().'/server/'.$this->server->uuid.')');

        return $message;
    }

    public function toTelegram(): array
    {
        $levelText = $this->level === 'critical' ? 'CRITICAL' : 'Warning';

        return [
            'message' => "Saturn Platform: [{$levelText}] Server '{$this->server->name}' high memory usage detected!\nMemory usage: ".round($this->memory_usage, 1)."%. Threshold: {$this->threshold}%.",
        ];
    }

    public function toPushover(): PushoverMessage
    {
        $levelText = $this->level === 'critical' ? 'Critical' : 'Warning';

        return new PushoverMessage(
            title: "{$levelText}: High memory usage detected",
            level: $this->level,
            message: "Server '{$this->server->name}' high memory usage detected!<br/><br/><b>Memory usage:</b> ".round($this->memory_usage, 1)."%.<br/><b>Threshold:</b> {$this->threshold}%.",
            buttons: [
                'View server' => base_url().'/server/'.$this->server->uuid,
            ],
        );
    }

    public function toSlack(): SlackMessage
    {
        $levelText = $this->level === 'critical' ? 'CRITICAL' : 'Warning';
        $color = $this->level === 'critical' ? SlackMessage::errorColor() : SlackMessage::warningColor();

        $description = "[{$levelText}] Server '{$this->server->name}' high memory usage detected!\n";
        $description .= 'Memory usage: '.round($this->memory_usage, 1)."%\n";
        $description .= "Threshold: {$this->threshold}%\n\n";
        $description .= 'View server: '.base_url().'/server/'.$this->server->uuid;

        return new SlackMessage(
            title: 'High memory usage detected',
            description: $description,
            color: $color
        );
    }

    public function toWebhook(): array
    {
        return [
            'success' => false,
            'message' => 'High memory usage detected',
            'event' => 'high_memory_usage',
            'level' => $this->level,
            'server_name' => $this->server->name,
            'server_uuid' => $this->server->uuid,
            'memory_usage' => round($this->memory_usage, 1),
            'threshold' => $this->threshold,
            'url' => base_url().'/server/'.$this->server->uuid,
        ];
    }
}
