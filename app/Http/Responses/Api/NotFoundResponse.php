<?php

namespace App\Http\Responses\Api;

use App\ApiResponse;
use Illuminate\Contracts\Support\Responsable;
use Symfony\Component\HttpFoundation\Response;

readonly class NotFoundResponse implements Responsable
{
    public function __construct(
        private string $message = 'Resource not found.',
    ) {}

    public function toResponse($request): Response
    {
        return ApiResponse::notFound($this->message);
    }
}
