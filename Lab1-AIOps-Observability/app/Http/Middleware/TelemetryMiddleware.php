<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class TelemetryMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $correlationId = $request->header('X-Request-Id', Str::uuid()->toString());
        $startTime = microtime(true);
        $response = $next($request);
        $latency = (microtime(true) - $startTime) * 1000;
        
        $routeName = $request->route() ? $request->route()->getName() : $request->path();
        $payloadSize = strlen($request->getContent());
        $responseSize = strlen($response->getContent());
        
        $severity = $response->isSuccessful() ? 'info' : 'error';
        $errorCategory = null;
        
        if ($latency > 4000) {
            $errorCategory = 'TIMEOUT_ERROR';
            $severity = 'error';
        } elseif (!$response->isSuccessful()) {
            $errorCategory = $this->getErrorCategoryFromResponse($response);
        }
        
        Log::channel('single')->info('api_request', [
            'correlation_id' => $correlationId,
            'method' => $request->method(),
            'path' => $request->path(),
            'route_name' => $routeName,
            'status_code' => $response->getStatusCode(),
            'latency_ms' => round($latency, 2),
            'client_ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'query_string' => $request->getQueryString(),
            'payload_size_bytes' => $payloadSize,
            'response_size_bytes' => $responseSize,
            'severity' => $severity,
            'error_category' => $errorCategory,
            'build_version' => env('BUILD_VERSION', '1.0.0'),
            'host' => gethostname(),
        ]);
                // ==== ADD THIS ====
        $metricsController = app(\App\Http\Controllers\MetricsController::class);
        $metricsController->recordRequest(
            $request->method(),
            $request->path(),
            $response->getStatusCode(),
            $latency,
            $errorCategory
        );
        // ==== END ADD ====
        
        $response->headers->set('X-Request-Id', $correlationId);
        return $response;
    }
    
    private function getErrorCategoryFromResponse($response): string
    {
        return 'SYSTEM_ERROR';
    }
}$metricsController = app(\App\Http\Controllers\MetricsController::class);
$metricsController->recordRequest(
    $request->method(),
    $request->path(),
    $response->getStatusCode(),
    $latency,
    $errorCategory
);