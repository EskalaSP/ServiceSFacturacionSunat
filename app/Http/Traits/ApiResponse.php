<?php

namespace App\Http\Traits;

use Illuminate\Http\JsonResponse;

/**
 * Formato unificado de respuestas de la API en español.
 *
 * Estructura estándar:
 *   {
 *     "estado":  "exito" | "error",
 *     "mensaje": "texto humano",
 *     "datos":   {} | [] | null,
 *     "meta":    {}        (paginación/cursor/otros - opcional),
 *     "errores": {}        (solo cuando estado=error con detalles - opcional)
 *   }
 */
trait ApiResponse
{
    protected function success(mixed $datos = null, string $mensaje = 'OK', int $codigo = 200, ?array $meta = null): JsonResponse
    {
        $cuerpo = [
            'estado' => 'exito',
            'mensaje' => $mensaje,
            'datos' => $datos,
        ];

        if ($meta !== null) {
            $cuerpo['meta'] = $meta;
        }

        return response()->json($cuerpo, $codigo);
    }

    protected function created(mixed $datos = null, string $mensaje = 'Creado exitosamente'): JsonResponse
    {
        return $this->success($datos, $mensaje, 201);
    }

    protected function error(string $mensaje = 'Error', int $codigo = 400, mixed $errores = null): JsonResponse
    {
        $cuerpo = [
            'estado' => 'error',
            'mensaje' => $mensaje,
        ];

        if ($errores) {
            $cuerpo['errores'] = $errores;
        }

        return response()->json($cuerpo, $codigo);
    }

    protected function errorAccionable(
        string $mensaje,
        string $codigoError,
        ?array $siguienteAccion = null,
        int $codigo = 422,
    ): JsonResponse {
        $cuerpo = [
            'estado' => 'error',
            'mensaje' => $mensaje,
            'codigo_error' => $codigoError,
        ];

        if ($siguienteAccion !== null) {
            $cuerpo['siguiente_accion'] = $siguienteAccion;
        }

        return response()->json($cuerpo, $codigo);
    }
}
