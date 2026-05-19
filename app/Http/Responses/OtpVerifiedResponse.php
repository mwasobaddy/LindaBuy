<?php

namespace App\Http\Responses;

use App\ApiResponse;
use Illuminate\Contracts\Support\Responsable;
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
            return ApiResponse::success(['redirect' => $this->redirect]);
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __($this->toastMessage),
        ]);

        return redirect()->to($this->redirect);
    }
}
