<?php

namespace App\Http\Requests\Api\Notification;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTelegramNotificationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'telegram_enabled' => 'sometimes|boolean',
            'telegram_token' => 'sometimes|string|nullable',
            'telegram_chat_id' => 'sometimes|string|nullable',
        ];
    }
}
