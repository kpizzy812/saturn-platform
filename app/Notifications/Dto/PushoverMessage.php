<?php

namespace App\Notifications\Dto;

use Illuminate\Support\Facades\Log;

class PushoverMessage
{
    public function __construct(
        public string $title,
        public string $message,
        public array $buttons = [],
        public string $level = 'info',
    ) {}

    public function getLevelIcon(): string
    {
        return match ($this->level) {
            'error' => "\xE2\x9D\x8C",
            'success' => "\xE2\x9C\x85",
            'warning' => "\xE2\x9A\xA0\xEF\xB8\x8F",
            default => "\xE2\x84\xB9\xEF\xB8\x8F",
        };
    }

    public function toPayload(string $token, string $user): array
    {
        $levelIcon = $this->getLevelIcon();
        $payload = [
            'token' => $token,
            'user' => $user,
            'title' => "{$levelIcon} {$this->title}",
            'message' => $this->message,
            'html' => 1,
        ];

        foreach ($this->buttons as $button) {
            $buttonUrl = data_get($button, 'url');
            $text = data_get($button, 'text', 'Click here');
            if ($buttonUrl && str_contains($buttonUrl, 'http://localhost')) {
                $buttonUrl = str_replace('http://localhost', config('app.url'), $buttonUrl);
            }
            $payload['message'] .= "&nbsp;<a href='".$buttonUrl."'>".$text.'</a>';
        }

        Log::info('Pushover message', $payload);

        return $payload;
    }
}
