<?php

namespace App\Http\Responses\Api;

use App\ApiResponse;
use Illuminate\Contracts\Support\Responsable;
use Symfony\Component\HttpFoundation\Response;

readonly class ErrorResponse implements Responsable
{
    public function __construct(
        private string $message,
        private int $status = 400,
        private string $code = '',
    ) {}

    public function toResponse($request): Response
    {
        return ApiResponse::error($this->message, $this->status, $this->code);
    }
}
