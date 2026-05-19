<?php

namespace App\Http\Responses;

use Illuminate\Contracts\Support\Responsable;
use Illuminate\Http\JsonResponse;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

readonly class OtpVerifiedResponse implements Responsable
{
    public function __construct(
        private string $redirect,
        private string $toastMessage = 'Logged in successfully!',
    ) {}

    public function toResponse($request): Response
    {
        if ($request->wantsJson()) {
            return new JsonResponse([
                'success' => true,
                'redirect' => $this->redirect,
            ]);
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __($this->toastMessage),
        ]);

        return redirect()->to($this->redirect);
    }
}
