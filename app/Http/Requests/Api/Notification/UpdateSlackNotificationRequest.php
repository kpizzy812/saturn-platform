<?php

namespace App\Http\Requests\Api\Notification;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSlackNotificationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'slack_enabled' => 'sometimes|boolean',
            'slack_webhook_url' => 'sometimes|url|nullable',
        ];
    }
}
