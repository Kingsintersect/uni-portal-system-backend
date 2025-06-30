<?php

namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * A list of exception types with their corresponding custom log levels.
     *
     * @var array<class-string<\Throwable>, \Psr\Log\LogLevel::*>
     */
    protected $levels = [
        //
    ];

    /**
     * A list of the exception types that are not reported.
     *
     * @var array<int, class-string<\Throwable>>
     */
    protected $dontReport = [
        //
    ];

    /**
     * A list of the inputs that are never flashed to the session on validation exceptions.
     *
     * @var array<int, string>
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Register the exception handling callbacks for the application.
     */
    public function register(): void
    {
        $this->reportable(function (Throwable $e) {
            //
        });
    }
    
     /**
     * Render an exception into an HTTP response.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Throwable  $exception
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function render($request, Throwable $exception)
    {
        // Handle unauthorized access via Spatie's Permission
        if ($exception instanceof \Spatie\Permission\Exceptions\UnauthorizedException) {
            return response()->json(['message' => 'You do not have permission to access this action.'], 403);
        }

        // Handle ThrottleRequestsException (429 Too Many Requests)
        if ($exception instanceof ThrottleRequestsException) {
            Log::info($exception->getMessage(), [
                'exception' => $exception
            ]);
            return response()->json([
                'message' => 'Too many requests. Please try again later.',
                'retry_after' => $exception->getHeaders()['Retry-After'] ?? 60,
            ], 429);
        }

        // Handle Not Found (404)
        if ($exception instanceof NotFoundHttpException) {
            return response()->json([
                'status' => 404,
                'response' => 'Not Found',
                'message' => 'Route Not Found or Does not Exist'
            ], 404);
        }

        // Handle Method Not Allowed (405)
        if ($exception instanceof MethodNotAllowedHttpException) {
            Log::info($exception->getMessage(), [
                'exception' => $exception
            ]);
            return response()->json([
                'error' => 'Bad Request',
                'message' => 'The request is invalid.',
            ], 405);
        }

        // Default exception handling (falling back to parent handler)
        return parent::render($request, $exception);
    }
}
