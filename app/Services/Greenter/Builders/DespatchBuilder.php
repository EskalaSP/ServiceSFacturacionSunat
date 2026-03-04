<?php

namespace App\Services\Greenter\Builders;

use App\Models\Tenant;
use DateTime;
use Greenter\Model\Client\Client;
use Greenter\Model\Company\Company;
use Greenter\Model\Despatch\Despatch;
use Greenter\Model\Despatch\DespatchDetail;
use Greenter\Model\Despatch\Direction;
use Greenter\Model\Despatch\Driver;
use Greenter\Model\Despatch\Shipment;
use Greenter\Model\Despatch\Transportist;
use Greenter\Model\Despatch\Vehicle;

class DespatchBuilder
{
    private Tenant $tenant;

    public function __construct(Tenant $tenant)
    {
        $this->tenant = $tenant;
    }

    public function build(array $data): Despatch
    {
        $despatch = new Despatch();

        $despatch
            ->setVersion('2022')
            ->setTipoDoc('09')
            ->setSerie($data['serie'])
            ->setCorrelativo((string) $data['correlativo'])
            ->setFechaEmision(new DateTime($data['fecha_emision']))
            ->setCompany($this->buildCompany());

        // Destinatario
        $despatch->setDestinatario(
            (new Client())
                ->setTipoDoc($data['destinatario']['tipo_doc'])
                ->setNumDoc($data['destinatario']['num_doc'])
                ->setRznSocial($data['destinatario']['razon_social'])
        );

        // Envío
        $shipment = new Shipment();
        $shipment
            ->setCodTraslado($data['cod_traslado'])
            ->setModTraslado($data['mod_traslado'])
            ->setFecTraslado(new DateTime($data['fecha_traslado']))
            ->setPesoTotal((float) $data['peso_total'])
            ->setUndPesoTotal($data['und_peso_total'] ?? 'KGM');

        if (! empty($data['num_bultos'])) {
            $shipment->setNumBultos((int) $data['num_bultos']);
        }

        // Direcciones
        $shipment->setLlegada(new Direction($data['llegada_ubigeo'], $data['llegada_direccion']));
        $shipment->setPartida(new Direction($data['partida_ubigeo'], $data['partida_direccion']));

        // Transportista (transporte público)
        if (! empty($data['transportista'])) {
            $transp = $data['transportista'];
            $transportist = (new Transportist())
                ->setTipoDoc($transp['tipo_doc'])
                ->setNumDoc($transp['num_doc'])
                ->setRznSocial($transp['razon_social']);

            if (! empty($transp['nro_mtc'])) {
                $transportist->setNroMtc($transp['nro_mtc']);
            }

            $shipment->setTransportista($transportist);
        }

        // Vehículo (transporte privado)
        if (! empty($data['vehiculo'])) {
            $shipment->setVehiculo(
                (new Vehicle())->setPlaca($data['vehiculo']['placa'])
            );
        }

        // Conductor
        if (! empty($data['conductor'])) {
            $cond = $data['conductor'];
            $driver = (new Driver())
                ->setTipoDoc($cond['tipo_doc'])
                ->setNroDoc($cond['num_doc']);

            if (! empty($cond['nombres'])) {
                $driver->setNombres($cond['nombres']);
            }
            if (! empty($cond['apellidos'])) {
                $driver->setApellidos($cond['apellidos']);
            }
            if (! empty($cond['licencia'])) {
                $driver->setNroLicencia($cond['licencia']);
            }

            $shipment->setChoferes([$driver]);
        }

        $despatch->setEnvio($shipment);

        // Items
        $details = [];
        foreach ($data['items'] as $item) {
            $details[] = (new DespatchDetail())
                ->setCantidad((float) $item['cantidad'])
                ->setUnidad($item['unidad'] ?? 'ZZ')
                ->setDescripcion($item['descripcion'])
                ->setCodigo($item['codigo'] ?? '');
        }
        $despatch->setDetails($details);

        return $despatch;
    }

    private function buildCompany(): Company
    {
        return (new Company())
            ->setRuc($this->tenant->ruc)
            ->setRazonSocial($this->tenant->razon_social);
    }
}
