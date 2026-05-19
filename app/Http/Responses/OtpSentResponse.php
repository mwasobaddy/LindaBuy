<?php

namespace App\Http\Responses;

use App\ApiResponse;
use Illuminate\Contracts\Support\Responsable;
use Symfony\Component\HttpFoundation\Response;

readonly class OtpSentResponse implements Responsable
{
    public function __construct(
        private string $message = 'OTP sent',
        private ?string $redirect = null,
    ) {}

    public function toResponse($request): Response
    {
        if ($request->wantsJson()) {
            $data = $this->redirect ? ['redirect' => $this->redirect] : [];

            return ApiResponse::success($data, $this->message);
        }

        if ($this->redirect) {
            return redirect()->to($this->redirect);
        }

        return redirect()->back()->with('toast', [
            'type' => 'success',
            'message' => $this->message,
        ]);
    }
}
