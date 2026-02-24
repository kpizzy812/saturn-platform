<?php

namespace App\Http\Requests\Api\Notification;

use Illuminate\Foundation\Http\FormRequest;

class UpdateEmailNotificationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'smtp_enabled' => 'sometimes|boolean',
            'smtp_from_address' => 'sometimes|email|nullable',
            'smtp_from_name' => 'sometimes|string|nullable',
            'smtp_recipients' => ['sometimes', 'string', 'nullable', 'regex:/^[^@\s]+@[^@\s]+(,[^@\s]+@[^@\s]+)*$/'],
            'smtp_host' => 'sometimes|string|nullable',
            'smtp_port' => 'sometimes|integer|nullable|min:1|max:65535',
            'smtp_username' => 'sometimes|string|nullable',
            'smtp_password' => 'sometimes|string|nullable',
            'resend_enabled' => 'sometimes|boolean',
            'resend_api_key' => 'sometimes|string|nullable',
        ];
    }
}
