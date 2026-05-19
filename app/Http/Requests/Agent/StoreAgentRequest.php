<?php

namespace App\Http\Requests\Agent;

use Illuminate\Foundation\Http\FormRequest;

class StoreAgentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === 'buyer';
    }

    public function rules(): array
    {
        return [
            'id_number' => ['required', 'string'],
            'kyc_photo' => ['required', 'image', 'max:2048'],
            'id_copy' => ['required', 'image', 'max:2048'],
        ];
    }
}
