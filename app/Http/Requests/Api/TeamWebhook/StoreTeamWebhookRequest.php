<?php

namespace App\Http\Requests\Api\TeamWebhook;

use App\Models\TeamWebhook;
use Illuminate\Foundation\Http\FormRequest;

class StoreTeamWebhookRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'url' => 'required|url|max:2048',
            'events' => 'required|array|min:1',
            'events.*' => 'string|in:'.implode(',', array_column(TeamWebhook::availableEvents(), 'value')),
        ];
    }
}
