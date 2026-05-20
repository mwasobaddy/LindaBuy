<?php

namespace App\Http\Responses\Api;

use App\ApiResponse;
use Illuminate\Contracts\Support\Responsable;
use Symfony\Component\HttpFoundation\Response;

readonly class OrderReleaseTokenResponse implements Responsable
{
    public function __construct(
        private string $message,
    ) {}

    public function toResponse($request): Response
    {
        return ApiResponse::success(['message' => $this->message], $this->message);
    }
}
