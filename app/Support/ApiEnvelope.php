<?php

namespace App\Support;

use Illuminate\Http\JsonResponse;

class ApiEnvelope
{
    public static function success(array $payload, int $status = 200): JsonResponse
    {
        return response()->json($payload, $status);
    }

    public static function error(string $code, string $message, int $status = 400, array $details = []): JsonResponse
    {
        return response()->json([
            'error' => [
                'code' => $code,
                'message' => $message,
                'details' => $details,
            ],
        ], $status);
    }
}
