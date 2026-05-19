<?php

namespace App\Http\Requests\Seller;

use Illuminate\Foundation\Http\FormRequest;

class StoreSellerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === 'buyer';
    }

    public function rules(): array
    {
        return [
            'shop_name' => ['required', 'string', 'max:255'],
            'shop_location' => ['sometimes', 'string', 'max:255'],
            'shop_location_coords_lat' => ['sometimes', 'numeric', 'between:-90,90'],
            'shop_location_coords_lng' => ['sometimes', 'numeric', 'between:-180,180'],
            'id_number' => ['required', 'string'],
            'kyc_photo' => ['required', 'image', 'max:2048'],
            'id_copy' => ['required', 'image', 'max:2048'],
        ];
    }
}
