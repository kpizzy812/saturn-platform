<?php

namespace App\Http\Requests\Api\TeamWebhook;

use App\Models\TeamWebhook;
use Illuminate\Foundation\Http\FormRequest;

class UpdateTeamWebhookRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'sometimes|string|max:255',
            'url' => 'sometimes|url|max:2048',
            'events' => 'sometimes|array|min:1',
            'events.*' => 'string|in:'.implode(',', array_column(TeamWebhook::availableEvents(), 'value')),
            'enabled' => 'sometimes|boolean',
        ];
    }
}
