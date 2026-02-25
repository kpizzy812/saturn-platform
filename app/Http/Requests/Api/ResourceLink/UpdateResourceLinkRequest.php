<?php

namespace App\Http\Requests\Api\ResourceLink;

use Illuminate\Foundation\Http\FormRequest;

class UpdateResourceLinkRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'inject_as' => 'nullable|string|max:255',
            'auto_inject' => 'boolean',
            'use_external_url' => 'boolean',
        ];
    }
}
