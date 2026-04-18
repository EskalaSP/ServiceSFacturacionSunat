<?php

namespace App\Services;

use App\Models\Tenant;
use Greenter\Sunat\ConsultaCpe\Api\AuthApi;
use Greenter\Sunat\ConsultaCpe\Api\ConsultaApi;
use Greenter\Sunat\ConsultaCpe\Configuration;
use Greenter\Sunat\ConsultaCpe\Model\CpeFilter;
use GuzzleHttp\Client as HttpClient;
use Illuminate\Support\Facades\Cache;

class SunatCpeConsultaService
{
    private const TOKEN_SCOPE = 'https://api.sunat.gob.pe/v1/contribuyente/contribuyentes';
    private const TOKEN_SAFETY_MARGIN = 60;

    private const ESTADO_CP = [
        '0' => 'NO EXISTE',
        '1' => 'ACEPTADO',
        '2' => 'ANULADO',
        '3' => 'AUTORIZADO',
        '4' => 'NO AUTORIZADO',
    ];

    private const ESTADO_RUC = [
        '00' => 'ACTIVO',
        '01' => 'BAJA PROVISIONAL',
        '02' => 'BAJA PROV. POR OFICIO',
        '03' => 'SUSPENSION TEMPORAL',
        '10' => 'BAJA DEFINITIVA',
        '11' => 'BAJA DE OFICIO',
        '22' => 'INHABILITADO-VENT.UNICA',
    ];

    private const COND_DOMI = [
        '00' => 'HABIDO',
        '09' => 'PENDIENTE',
        '11' => 'POR VERIFICAR',
        '12' => 'NO HABIDO',
        '20' => 'NO HALLADO',
    ];

    public function consultar(Tenant $tenant, array $filter): array
    {
        if (empty($tenant->client_id) || empty($tenant->client_secret)) {
            throw new \RuntimeException('El tenant no tiene configurado client_id/client_secret para consultar SUNAT.');
        }

        $token = $this->getToken($tenant);

        $config = Configuration::getDefaultConfiguration()
            ->setAccessToken($token)
            ->setHost(Configuration::getDefaultConfiguration()->getHostFromSettings(1));

        $api = new ConsultaApi(new HttpClient(), $config);

        $cpe = (new CpeFilter())
            ->setNumRuc($filter['ruc_emisor'])
            ->setCodComp($filter['tipo_doc'])
            ->setNumeroSerie($filter['serie'])
            ->setNumero((string) $filter['correlativo'])
            ->setFechaEmision($filter['fecha_emision'])
            ->setMonto(number_format((float) $filter['monto'], 2, '.', ''));

        $result = $api->consultarCpe($tenant->ruc, $cpe);

        if (! $result->getSuccess()) {
            return [
                'encontrado' => false,
                'mensaje' => $result->getMessage(),
            ];
        }

        $data = $result->getData();
        $estadoCp = $data->getEstadoCp();
        $estadoRuc = $data->getEstadoRuc();
        $condDomi = $data->getCondDomiRuc();

        return [
            'encontrado' => true,
            'estado_cp' => $estadoCp,
            'estado_cp_descripcion' => self::ESTADO_CP[$estadoCp] ?? 'DESCONOCIDO',
            'estado_ruc' => $estadoRuc,
            'estado_ruc_descripcion' => self::ESTADO_RUC[$estadoRuc] ?? 'DESCONOCIDO',
            'cond_domi_ruc' => $condDomi,
            'cond_domi_ruc_descripcion' => self::COND_DOMI[$condDomi] ?? 'DESCONOCIDO',
            'observaciones' => $data->getObservaciones() ?? [],
        ];
    }

    private function getToken(Tenant $tenant): string
    {
        $cacheKey = 'sunat_cpe_token:' . $tenant->id;

        $cached = Cache::get($cacheKey);
        if ($cached) {
            return $cached;
        }

        $auth = new AuthApi(new HttpClient());
        $result = $auth->getToken(
            'client_credentials',
            self::TOKEN_SCOPE,
            $tenant->client_id,
            $tenant->client_secret,
        );

        $ttl = max(60, ((int) $result->getExpiresIn()) - self::TOKEN_SAFETY_MARGIN);
        $token = $result->getAccessToken();

        Cache::put($cacheKey, $token, $ttl);

        return $token;
    }

    public function invalidateToken(Tenant $tenant): void
    {
        Cache::forget('sunat_cpe_token:' . $tenant->id);
    }
}
