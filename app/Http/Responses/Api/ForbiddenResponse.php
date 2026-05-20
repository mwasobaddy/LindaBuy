<?php

namespace App\Http\Responses\Api;

use App\ApiResponse;
use Illuminate\Contracts\Support\Responsable;
use Symfony\Component\HttpFoundation\Response;

readonly class ForbiddenResponse implements Responsable
{
    public function __construct(
        private string $message = 'Forbidden.',
    ) {}

    public function toResponse($request): Response
    {
        return ApiResponse::forbidden($this->message);
    }
}
