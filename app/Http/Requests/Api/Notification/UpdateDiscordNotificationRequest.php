<?php

namespace App\Http\Requests\Api\Notification;

use Illuminate\Foundation\Http\FormRequest;

class UpdateDiscordNotificationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'discord_enabled' => 'sometimes|boolean',
            'discord_webhook_url' => 'sometimes|url|nullable',
        ];
    }
}
