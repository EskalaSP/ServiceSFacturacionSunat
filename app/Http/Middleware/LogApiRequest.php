<?php

namespace App\Http\Middleware;

use App\Models\ApiLog;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class LogApiRequest
{
    public function handle(Request $request, Closure $next): Response
    {
        $start = microtime(true);

        $response = $next($request);

        $duration = (int) ((microtime(true) - $start) * 1000);

        // Only decode/store a truncated summary of the response to avoid memory overhead
        $responseContent = $response->getContent();
        $responseBody = null;
        if (strlen($responseContent) <= 4096) {
            $responseBody = json_decode($responseContent, true);
        } else {
            $decoded = json_decode($responseContent, true);
            $responseBody = [
                'success' => $decoded['success'] ?? null,
                'message' => $decoded['message'] ?? null,
                '_truncated' => true,
                '_size' => strlen($responseContent),
            ];
        }

        $logData = [
            'tenant_id' => $request->get('tenant')?->id,
            'endpoint' => $request->path(),
            'method' => $request->method(),
            'request_body' => $request->except(['tenant']),
            'response_body' => $responseBody,
            'status_code' => $response->getStatusCode(),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'duration_ms' => $duration,
            'created_at' => now(),
        ];

        dispatch(function () use ($logData) {
            ApiLog::create($logData);
        })->afterResponse();

        return $response;
    }
}
