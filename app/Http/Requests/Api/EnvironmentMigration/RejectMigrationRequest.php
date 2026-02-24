<?php

namespace App\Http\Requests\Api\EnvironmentMigration;

use Illuminate\Foundation\Http\FormRequest;

class RejectMigrationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'reason' => 'required|string|max:1000',
        ];
    }
}
