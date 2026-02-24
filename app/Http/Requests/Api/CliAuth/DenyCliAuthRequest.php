<?php

namespace App\Http\Requests\Api\CliAuth;

use Illuminate\Foundation\Http\FormRequest;

class DenyCliAuthRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'code' => 'required|string|size:9',
        ];
    }
}
