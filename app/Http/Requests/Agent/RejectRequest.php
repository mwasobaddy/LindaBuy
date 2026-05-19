<?php

namespace App\Http\Requests\Agent;

use Illuminate\Foundation\Http\FormRequest;

class RejectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermissionTo('reject-agents');
    }

    public function rules(): array
    {
        return [
            'rejection_reason' => ['required', 'string', 'max:1000'],
        ];
    }
}
