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

        $tenant = $request->get('tenant');

        ApiLog::create([
            'tenant_id' => $tenant?->id,
            'endpoint' => $request->path(),
            'method' => $request->method(),
            'request_body' => $request->except(['tenant']),
            'response_body' => json_decode($response->getContent(), true),
            'status_code' => $response->getStatusCode(),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'duration_ms' => $duration,
            'created_at' => now(),
        ]);

        return $response;
    }
}
