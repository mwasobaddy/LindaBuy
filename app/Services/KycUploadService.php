<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;

class KycUploadService
{
    public function upload(UploadedFile $file, int $userId, string $type): string
    {
        $extension = $file->getClientOriginalExtension();

        $path = $file->storeAs(
            "kyc/{$userId}",
            "{$type}.{$extension}",
            'public'
        );

        return $path;
    }
}
