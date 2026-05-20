<?php

namespace App\Http\Responses\Api;

use App\ApiResponse;
use Illuminate\Contracts\Support\Responsable;
use Symfony\Component\HttpFoundation\Response;

readonly class OrderCreatedResponse implements Responsable
{
    public function __construct(
        private mixed $order,
        private string $message,
        private ?string $checkoutRequestId = null,
    ) {}

    public function toResponse($request): Response
    {
        $data = $this->order;

        if (is_object($this->order) && method_exists($this->order, 'toArray')) {
            $data = $this->order->toArray();
        }

        if ($this->checkoutRequestId !== null) {
            $data['checkout_request_id'] = $this->checkoutRequestId;
        }

        return ApiResponse::created($data, $this->message);
    }
}
