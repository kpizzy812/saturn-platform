<?php

namespace App\Http\Requests\Api\CliAuth;

use Illuminate\Foundation\Http\FormRequest;

class ApproveCliAuthRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'code' => 'required|string|size:9',
            'team_id' => 'required|integer|exists:teams,id',
        ];
    }
}
