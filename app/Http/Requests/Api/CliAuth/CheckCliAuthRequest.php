<?php

namespace App\Http\Requests\Api\CliAuth;

use Illuminate\Foundation\Http\FormRequest;

class CheckCliAuthRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'secret' => 'required|string|size:40',
        ];
    }
}
