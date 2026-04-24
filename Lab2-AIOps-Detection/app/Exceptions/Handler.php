<?php

namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Throwable;
use Illuminate\Database\QueryException;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;

class Handler extends ExceptionHandler
{
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    public function register(): void
    {
        $this->reportable(function (Throwable $e) {
            //
        });
    }

    public function render($request, Throwable $e)
    {
        $errorCategory = $this->categorizeError($e);
        
        return parent::render($request, $e)->withHeaders([
            'X-Error-Category' => $errorCategory,
        ]);
    }

    private function categorizeError(Throwable $e): string
    {
        if ($e instanceof ValidationException) {
            return 'VALIDATION_ERROR';
        }
        
        if ($e instanceof QueryException) {
            return 'DATABASE_ERROR';
        }
        
        if ($e instanceof HttpException && $e->getStatusCode() >= 500) {
            return 'SYSTEM_ERROR';
        }
        
        if ($e instanceof NotFoundHttpException) {
            return 'SYSTEM_ERROR';
        }
        
        return 'UNKNOWN';
    }
}