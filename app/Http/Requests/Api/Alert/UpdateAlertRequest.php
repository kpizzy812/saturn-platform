<?php

namespace App\Http\Requests\Api\Alert;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAlertRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'sometimes|string|max:255',
            'metric' => 'sometimes|string|in:cpu,memory,disk,error_rate,response_time',
            'condition' => 'sometimes|string|in:>,<,=',
            'threshold' => 'sometimes|numeric|min:0',
            'duration' => 'sometimes|integer|min:1|max:1440',
            'enabled' => 'sometimes|boolean',
            'channels' => 'nullable|array',
            'channels.*' => 'string|in:email,slack,discord,telegram,pagerduty,webhook',
        ];
    }
}
