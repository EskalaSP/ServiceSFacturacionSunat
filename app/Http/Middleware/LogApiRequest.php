<?php

namespace App\Http\Middleware;

use App\Models\ApiLog;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Symfony\Component\HttpFoundation\Response;

class LogApiRequest
{
    public function handle(Request $request, Closure $next): Response
    {
        $start = microtime(true);

        $response = $next($request);

        $duration = (int) ((microtime(true) - $start) * 1000);

        // Solo decodificar/almacenar un resumen truncado de la respuesta para evitar sobrecarga de memoria
        $responseContent = $response->getContent();
        $responseBody = null;
        if (strlen($responseContent) <= 4096) {
            $responseBody = json_decode($responseContent, true);
        } else {
            $decoded = json_decode($responseContent, true);
            $responseBody = [
                'estado' => $decoded['estado'] ?? null,
                'mensaje' => $decoded['mensaje'] ?? null,
                '_truncated' => true,
                '_size' => strlen($responseContent),
            ];
        }

        $logData = [
            'tenant_id' => $request->get('tenant')?->id,
            'endpoint' => $request->path(),
            'method' => $request->method(),
            'request_body' => $this->sanitizeRequestBody($request->except(['tenant'])),
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

    /**
     * Reemplaza UploadedFile con metadata serializable y oculta datos sensibles.
     * Sin esto, multipart/form-data (logos, certificados) revienta el afterResponse().
     */
    private function sanitizeRequestBody(array $body): array
    {
        $sensitive = ['contrasena_certificado', 'sol_pass', 'password', 'api_secret'];

        array_walk_recursive($body, function (&$value, $key) use ($sensitive) {
            if ($value instanceof UploadedFile) {
                $value = [
                    '_file' => true,
                    'name' => $value->getClientOriginalName(),
                    'size' => $value->getSize(),
                    'mime' => $value->getClientMimeType(),
                ];
            } elseif (in_array($key, $sensitive, true) && !empty($value)) {
                $value = '***';
            }
        });

        return $body;
    }
}
