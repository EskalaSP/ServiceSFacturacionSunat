<?php

namespace App\Services\Greenter\Builders;

use App\Models\Tenant;
use DateTime;
use DateTimeZone;
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
            ->setFechaEmision(new DateTime($data['fecha_emision'], new DateTimeZone('America/Lima')))
            ->setCompany($this->buildCompany());

        // Observación
        if (! empty($data['observacion'])) {
            $despatch->setObservacion($data['observacion']);
        }

        // Destinatario
        $despatch->setDestinatario(
            (new Client())
                ->setTipoDoc($data['destinatario']['tipo_doc'])
                ->setNumDoc($data['destinatario']['num_doc'])
                ->setRznSocial($data['destinatario']['razon_social'])
        );

        // Tercero (proveedor)
        if (! empty($data['tercero'])) {
            $despatch->setTercero(
                (new Client())
                    ->setTipoDoc($data['tercero']['tipo_doc'])
                    ->setNumDoc($data['tercero']['num_doc'])
                    ->setRznSocial($data['tercero']['razon_social'])
            );
        }

        // Comprador
        if (! empty($data['comprador'])) {
            $despatch->setComprador(
                (new Client())
                    ->setTipoDoc($data['comprador']['tipo_doc'])
                    ->setNumDoc($data['comprador']['num_doc'])
                    ->setRznSocial($data['comprador']['razon_social'])
            );
        }

        // Envío
        $shipment = $this->buildShipment($data);
        $despatch->setEnvio($shipment);

        // Items
        $details = [];
        foreach ($data['items'] as $item) {
            $detail = (new DespatchDetail())
                ->setCantidad((float) $item['cantidad'])
                ->setUnidad($item['unidad'] ?? 'ZZ')
                ->setDescripcion($item['descripcion'])
                ->setCodigo($item['codigo'] ?? '');

            if (! empty($item['cod_prod_sunat'])) {
                $detail->setCodProdSunat($item['cod_prod_sunat']);
            }

            $details[] = $detail;
        }
        $despatch->setDetails($details);

        return $despatch;
    }

    private function buildShipment(array $data): Shipment
    {
        $shipment = new Shipment();
        $shipment
            ->setCodTraslado($data['cod_traslado'])
            ->setModTraslado($data['mod_traslado'])
            ->setFecTraslado(new DateTime($data['fecha_traslado'], new DateTimeZone('America/Lima')))
            ->setPesoTotal((float) $data['peso_total'])
            ->setUndPesoTotal($data['und_peso_total'] ?? 'KGM');

        if (! empty($data['num_bultos'])) {
            $shipment->setNumBultos((int) $data['num_bultos']);
        }

        // Indicadores (M1L, transbordo, retorno vacío, etc.)
        if (! empty($data['indicadores'])) {
            $shipment->setIndicadores($data['indicadores']);
        }

        // Direcciones
        $llegada = new Direction($data['llegada_ubigeo'], $data['llegada_direccion']);
        if (! empty($data['llegada_ruc'])) {
            $llegada->setRuc($data['llegada_ruc']);
        }
        if (! empty($data['llegada_cod_local'])) {
            $llegada->setCodLocal($data['llegada_cod_local']);
        }

        $partida = new Direction($data['partida_ubigeo'], $data['partida_direccion']);
        if (! empty($data['partida_ruc'])) {
            $partida->setRuc($data['partida_ruc']);
        }
        if (! empty($data['partida_cod_local'])) {
            $partida->setCodLocal($data['partida_cod_local']);
        }

        $shipment->setLlegada($llegada);
        $shipment->setPartida($partida);

        // Transportista (transporte público)
        if (! empty($data['transportista'])) {
            $shipment->setTransportista($this->buildTransportist($data['transportista']));
        }

        // Vehículo (transporte privado)
        if (! empty($data['vehiculo'])) {
            $shipment->setVehiculo($this->buildVehicle($data['vehiculo']));
        }

        // Conductores
        if (! empty($data['conductor'])) {
            $shipment->setChoferes([$this->buildDriver($data['conductor'])]);
        }
        if (! empty($data['conductores'])) {
            $drivers = array_map(fn ($c) => $this->buildDriver($c), $data['conductores']);
            $shipment->setChoferes($drivers);
        }

        return $shipment;
    }

    private function buildTransportist(array $transp): Transportist
    {
        $transportist = (new Transportist())
            ->setTipoDoc($transp['tipo_doc'])
            ->setNumDoc($transp['num_doc'])
            ->setRznSocial($transp['razon_social']);

        if (! empty($transp['nro_mtc'])) {
            $transportist->setNroMtc($transp['nro_mtc']);
        }

        return $transportist;
    }

    private function buildVehicle(array $veh): Vehicle
    {
        $vehicle = (new Vehicle())->setPlaca($veh['placa']);

        // Vehículos secundarios
        if (! empty($veh['secundarios'])) {
            $secundarios = array_map(
                fn ($s) => (new Vehicle())->setPlaca($s['placa']),
                $veh['secundarios']
            );
            $vehicle->setSecundarios($secundarios);
        }

        return $vehicle;
    }

    private function buildDriver(array $cond): Driver
    {
        $driver = (new Driver())
            ->setTipoDoc($cond['tipo_doc'])
            ->setNroDoc($cond['num_doc']);

        if (! empty($cond['tipo'])) {
            $driver->setTipo($cond['tipo']);
        }
        if (! empty($cond['nombres'])) {
            $driver->setNombres($cond['nombres']);
        }
        if (! empty($cond['apellidos'])) {
            $driver->setApellidos($cond['apellidos']);
        }
        if (! empty($cond['licencia'])) {
            $driver->setLicencia($cond['licencia']);
        }

        return $driver;
    }

    private function buildCompany(): Company
    {
        return (new Company())
            ->setRuc($this->tenant->ruc)
            ->setRazonSocial($this->tenant->razon_social);
    }
}
