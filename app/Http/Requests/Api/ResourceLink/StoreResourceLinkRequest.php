<?php

namespace App\Http\Requests\Api\ResourceLink;

use Illuminate\Foundation\Http\FormRequest;

class StoreResourceLinkRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'source_id' => 'required|integer',
            'target_type' => 'required|string|in:postgresql,mysql,mariadb,redis,keydb,dragonfly,mongodb,clickhouse,application',
            'target_id' => 'required|integer',
            'inject_as' => 'nullable|string|max:255',
            'auto_inject' => 'boolean',
            'use_external_url' => 'boolean',
        ];
    }
}
