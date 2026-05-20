<?php

namespace App\Http\Responses\Api;

use App\ApiResponse;
use Illuminate\Contracts\Support\Responsable;
use Symfony\Component\HttpFoundation\Response;

readonly class DeletedResponse implements Responsable
{
    public function __construct(
        private string $message = 'Deleted successfully.',
    ) {}

    public function toResponse($request): Response
    {
        return ApiResponse::deleted($this->message);
    }
}
