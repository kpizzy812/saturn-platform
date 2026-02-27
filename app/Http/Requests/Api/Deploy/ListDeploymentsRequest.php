<?php

namespace App\Http\Requests\Api\Deploy;

use Illuminate\Foundation\Http\FormRequest;

class ListDeploymentsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'skip' => ['nullable', 'integer', 'min:0'],
            'take' => ['nullable', 'integer', 'min:1', 'max:500'],
        ];
    }
}
