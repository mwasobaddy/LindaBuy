<?php

namespace App\Http\Responses;

use Illuminate\Contracts\Support\Responsable;
use Illuminate\Http\JsonResponse;
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
            $data = ['success' => true, 'message' => $this->message];

            if ($this->redirect) {
                $data['redirect'] = $this->redirect;
            }

            return new JsonResponse($data);
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
