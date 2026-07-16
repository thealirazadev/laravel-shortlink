<?php

namespace App\Support;

use Illuminate\Http\JsonResponse;

/**
 * Builds the single JSON success/error envelope used by every API response.
 */
class ApiResponse
{
    public static function success(mixed $data, ?array $meta = null, int $status = 200): JsonResponse
    {
        $payload = ['data' => $data];

        if ($meta !== null) {
            $payload['meta'] = $meta;
        }

        return response()->json($payload, $status);
    }

    public static function error(string $code, string $message, ?array $details = null, int $status = 400): JsonResponse
    {
        $error = [
            'code' => $code,
            'message' => $message,
        ];

        if ($details !== null) {
            $error['details'] = $details;
        }

        return response()->json(['error' => $error], $status);
    }
}
