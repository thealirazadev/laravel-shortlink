<?php

namespace App\Exceptions;

use App\Support\ApiResponse;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Throwable;

/**
 * Renders every API exception into the single { "error": { code, message, details } } envelope.
 * Non-API requests (the public redirect route) are left to their own controller logic/views.
 */
class Handler
{
    public static function render(Throwable $e, Request $request): ?JsonResponse
    {
        if (! $request->is('api/*') && ! $request->expectsJson()) {
            return null;
        }

        return match (true) {
            $e instanceof ValidationException => ApiResponse::error(
                'validation_failed',
                'The given data was invalid.',
                $e->errors(),
                422,
            ),
            $e instanceof AuthenticationException => ApiResponse::error(
                'unauthenticated',
                'Authentication is required.',
                status: 401,
            ),
            $e instanceof AuthorizationException, $e instanceof AccessDeniedHttpException => ApiResponse::error(
                'forbidden',
                'You are not authorized to perform this action.',
                status: 403,
            ),
            $e instanceof ModelNotFoundException, $e instanceof NotFoundHttpException => ApiResponse::error(
                'not_found',
                'The requested resource was not found.',
                status: 404,
            ),
            $e instanceof TooManyRequestsHttpException => ApiResponse::error(
                'rate_limited',
                'Too many requests.',
                status: 429,
            ),
            $e instanceof HttpExceptionInterface => ApiResponse::error(
                'server_error',
                'An unexpected error occurred.',
                status: $e->getStatusCode(),
            ),
            default => self::renderUnexpected($e),
        };
    }

    private static function renderUnexpected(Throwable $e): JsonResponse
    {
        Log::error('exception.unhandled', [
            'exception' => $e::class,
            'message' => $e->getMessage(),
        ]);

        return ApiResponse::error(
            'server_error',
            'An unexpected error occurred.',
            status: 500,
        );
    }
}
