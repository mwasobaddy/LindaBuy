<?php

namespace App\Http\Responses\Api;

use App\ApiResponse;
use Illuminate\Contracts\Support\Responsable;
use Symfony\Component\HttpFoundation\Response;

readonly class CreatedResponse implements Responsable
{
    public function __construct(
        private mixed $data = [],
        private string $message = 'Created successfully.',
    ) {}

    public function toResponse($request): Response
    {
        return ApiResponse::created($this->data, $this->message);
    }
}
