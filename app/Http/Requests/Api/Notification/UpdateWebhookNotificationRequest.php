<?php

namespace App\Http\Requests\Api\Notification;

use Illuminate\Foundation\Http\FormRequest;

class UpdateWebhookNotificationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'webhook_enabled' => 'sometimes|boolean',
            'webhook_url' => 'sometimes|url|nullable',
        ];
    }
}
