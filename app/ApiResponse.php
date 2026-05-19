<?php

namespace App;

use Illuminate\Http\JsonResponse;

class ApiResponse
{
    public static function success(mixed $data = [], string $message = '', int $status = 200, string $code = ''): JsonResponse
    {
        return response()->json([
            'data' => $data,
            'meta' => array_filter([
                'message' => $message,
                'code' => $code,
            ]),
        ], $status);
    }

    public static function created(mixed $data = [], string $message = 'Created successfully.'): JsonResponse
    {
        return static::success($data, $message, 201);
    }

    public static function deleted(string $message = 'Deleted successfully.'): JsonResponse
    {
        return static::success([], $message, 200);
    }

    public static function error(string $message, int $status = 400, string $code = ''): JsonResponse
    {
        return response()->json([
            'errors' => [
                ['field' => null, 'message' => $message],
            ],
            'meta' => array_filter([
                'code' => $code,
            ]),
        ], $status);
    }

    public static function notFound(string $message = 'Resource not found.'): JsonResponse
    {
        return static::error($message, 404, 'NOT_FOUND');
    }

    public static function forbidden(string $message = 'Forbidden.'): JsonResponse
    {
        return static::error($message, 403, 'FORBIDDEN');
    }
}
