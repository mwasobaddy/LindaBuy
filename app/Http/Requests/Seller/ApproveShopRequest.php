<?php

namespace App\Http\Requests\Seller;

use Illuminate\Foundation\Http\FormRequest;

class ApproveShopRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermissionTo('approve-sellers');
    }

    public function rules(): array
    {
        return [];
    }
}
