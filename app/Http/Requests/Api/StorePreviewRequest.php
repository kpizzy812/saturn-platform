<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class StorePreviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'pull_request_id' => ['required', 'integer', 'min:1'],
            'pull_request_url' => ['nullable', 'url', 'max:2048'],
            'pull_request_html_url' => ['nullable', 'url', 'max:2048'],
        ];
    }
}
