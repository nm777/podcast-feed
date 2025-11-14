<?php

namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * A list of exception types with their corresponding custom log levels.
     */
    protected $levels = [
        'emergency' => 'emergency',
        'alert' => 'alert',
        'critical' => 'critical',
        'error' => 'error',
        'warning' => 'warning',
        'notice' => 'notice',
        'info' => 'info',
        'debug' => 'debug',
    ];

    /**
     * A list of the exception types that should not be reported.
     */
    protected $dontReport = [
        //
    ];

    /**
     * A list of the inputs that are never flashed to the session on validation exceptions.
     */
    protected $dontFlash = [
        'current_password',
        'current_password_confirmation',
        'password',
        'password_confirmation',
        'new_password',
        'new_password_confirmation',
    ];

    /**
     * Register the exception handling callbacks for the application.
     */
    public function register()
    {
        $this->reportable(function (Throwable $e) {
            if (app()->bound('sentry')) {
                app('sentry')->captureException($e);
            }
        });

        $this->renderable(function (Throwable $e, Request $request) {
            return $this->handleApiException($e, $request);
        });
    }

    /**
     * Render an exception into an HTTP response for API requests.
     */
    protected function handleApiException(Throwable $e, Request $request): JsonResponse
    {
        if ($request->is('api/*')) {
            return $this->renderApiJsonResponse($e);
        }

        return parent::render($request, $e);
    }

    /**
     * Create standardized API error response.
     */
    protected function renderApiJsonResponse(Throwable $e): JsonResponse
    {
        $status = 500;
        $error = [
            'error' => 'Internal Server Error',
            'message' => 'An unexpected error occurred. Please try again later.',
            'code' => 'INTERNAL_ERROR',
        ];

        // Handle specific exception types
        if ($e instanceof \Illuminate\Auth\AuthenticationException) {
            $status = 401;
            $error = [
                'error' => 'Unauthenticated',
                'message' => 'Authentication is required to access this resource.',
                'code' => 'UNAUTHENTICATED',
            ];
        } elseif ($e instanceof \Illuminate\Auth\Access\AuthorizationException) {
            $status = 403;
            $error = [
                'error' => 'Forbidden',
                'message' => 'You do not have permission to perform this action.',
                'code' => 'FORBIDDEN',
            ];
        } elseif ($e instanceof \Illuminate\Database\Eloquent\ModelNotFoundException) {
            $status = 404;
            $error = [
                'error' => 'Not Found',
                'message' => 'The requested resource was not found.',
                'code' => 'NOT_FOUND',
            ];
        } elseif ($e instanceof \Symfony\Component\HttpKernel\Exception\NotFoundHttpException) {
            $status = 404;
            $error = [
                'error' => 'Not Found',
                'message' => 'The requested endpoint was not found.',
                'code' => 'ENDPOINT_NOT_FOUND',
            ];
        } elseif ($e instanceof \Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException) {
            $status = 405;
            $error = [
                'error' => 'Method Not Allowed',
                'message' => 'The HTTP method is not allowed for this endpoint.',
                'code' => 'METHOD_NOT_ALLOWED',
            ];
        } elseif ($e instanceof \Illuminate\Http\Exceptions\ThrottleRequestsException) {
            $status = 429;
            $error = [
                'error' => 'Too Many Requests',
                'message' => 'Too many requests. Please try again later.',
                'code' => 'TOO_MANY_REQUESTS',
            ];
        } elseif ($e instanceof ApiException) {
            $status = $e->getCode() ?: 400;
            $error = [
                'error' => $e->getErrorType() ?: 'API Error',
                'message' => $e->getMessage(),
                'code' => $e->getErrorCode() ?: 'API_ERROR',
            ];
        } elseif ($e instanceof ValidationException) {
            $status = 422;
            $error = [
                'error' => 'Validation Failed',
                'message' => 'The given data was invalid.',
                'code' => 'VALIDATION_FAILED',
                'errors' => $e->errors(),
            ];
        }

        // Include debug info in local environment
        if (config('app.debug') && ! ($e instanceof ValidationException)) {
            $error['debug'] = [
                'exception' => get_class($e),
                'message' => $e->getMessage(),
                'trace' => collect($e->getTrace())->map(function ($trace) {
                    return array_intersect_key($trace, ['file', 'line', 'class', 'function']);
                })->all(),
            ];
        }

        return response()->json($error, $status);
    }
}
