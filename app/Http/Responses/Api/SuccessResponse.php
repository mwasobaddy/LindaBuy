<?php

namespace App\Http\Responses\Api;

use App\ApiResponse;
use Illuminate\Contracts\Support\Responsable;
use Symfony\Component\HttpFoundation\Response;

readonly class SuccessResponse implements Responsable
{
    public function __construct(
        private mixed $data = [],
        private string $message = '',
        private int $status = 200,
    ) {}

    public function toResponse($request): Response
    {
        return ApiResponse::success($this->data, $this->message, $this->status);
    }
}
