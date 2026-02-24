<?php

namespace App\Http\Requests\Api\Notification;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePushoverNotificationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'pushover_enabled' => 'sometimes|boolean',
            'pushover_user_key' => 'sometimes|string|nullable',
            'pushover_api_token' => 'sometimes|string|nullable',
        ];
    }
}
