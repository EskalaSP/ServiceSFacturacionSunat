<?php

namespace App\Services\Greenter\Builders;

use App\Models\Tenant;
use DateTime;
use DateTimeZone;
use Greenter\Model\Client\Client;
use Greenter\Model\Company\Company;
use Greenter\Model\Despatch\AdditionalDoc;
use Greenter\Model\Despatch\Despatch;
use Greenter\Model\Despatch\DespatchDetail;
use Greenter\Model\Despatch\Direction;
use Greenter\Model\Despatch\Driver;
use Greenter\Model\Despatch\Shipment;
use Greenter\Model\Despatch\Transportist;
use Greenter\Model\Despatch\Vehicle;

/**
 * Arma objetos Greenter para Guías de Remisión — ambas variantes:
 *   - '09' Guía de Remisión Remitente (GRR) — emite el que envía
 *   - '31' Guía de Remisión Transportista (GRT) — emite la empresa de transporte
 *
 * Diferencias:
 *   GRR: DespatchSupplierParty = remitente (empresa del tenant)
 *        DeliveryCustomerParty  = destinatario final
 *        SellerSupplierParty    = tercero (opcional)
 *        addDocs                = documentos referenciados (opcionales)
 *
 *   GRT: DespatchSupplierParty = transportista (empresa del tenant)
 *        DeliveryCustomerParty = destinatario final
 *        Shipment/Delivery/Despatch/DespatchParty = remitente
 *        addDocs                = documentos referenciados (OBLIGATORIO)
 */
class DespatchBuilder
{
    private Tenant $tenant;

    public function __construct(Tenant $tenant)
    {
        $this->tenant = $tenant;
    }

    public function build(array $data): Despatch
    {
        $tipoDocumento = $data['tipo_documento'] ?? '09';
        $despatch = new Despatch();

        $despatch
            ->setVersion('2022')
            ->setTipoDoc($tipoDocumento)
            ->setSerie($data['serie'])
            ->setCorrelativo((string) $data['correlativo'])
            ->setFechaEmision(new DateTime($data['fecha_emision'], new DateTimeZone('America/Lima')))
            ->setCompany($this->buildCompany());

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

        // Tercero (proveedor) — útil en GRR
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

        // Documentos relacionados
        //   - GRR: opcional — puede referenciar facturas, guías, DAM, etc.
        //   - GRT: OBLIGATORIO — SUNAT exige al menos un documento (factura/GRR/DAM/etc.)
        $addDocs = [];
        if (! empty($data['doc_relacionado'])) {
            $rels = $this->normalizeDocRelacionado($data['doc_relacionado']);
            foreach ($rels as $rel) {
                $doc = (new AdditionalDoc())
                    ->setTipo($rel['tipo_codigo'])
                    ->setNro($rel['numero']);
                if (! empty($rel['tipo_descripcion'])) {
                    $doc->setTipoDesc($rel['tipo_descripcion']);
                }
                if (! empty($rel['ruc_emisor'])) {
                    $doc->setEmisor($rel['ruc_emisor']);
                }
                $addDocs[] = $doc;
            }
        }
        if (! empty($addDocs)) {
            $despatch->setAddDocs($addDocs);
        }

        // Envío
        $despatch->setEnvio($this->buildShipment($data, $tipoDocumento));

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

    /**
     * Permite que doc_relacionado sea un objeto único o array de objetos.
     */
    private function normalizeDocRelacionado(array $rel): array
    {
        if (empty($rel)) return [];
        // Si viene con claves asociativas (un solo doc)
        if (isset($rel['tipo_codigo']) || isset($rel['numero'])) {
            return [$rel];
        }
        // Si viene como array de docs
        return $rel;
    }

    private function buildShipment(array $data, string $tipoDocumento): Shipment
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

        // Indicadores (M1L, transbordo, retorno vacío, pagador flete, subcontratado, traslado total)
        $indicadores = $data['indicadores'] ?? [];

        // Auto-derivar indicadores de campos semánticos (opcional)
        if (! empty($data['datos_pagador_flete']['tipo'])) {
            $tipo = $data['datos_pagador_flete']['tipo'];
            $map = [
                'remitente' => 'SUNAT_Envio_IndicadorPagadorFlete_Remitente',
                'subcontratador' => 'SUNAT_Envio_IndicadorPagadorFlete_Subcontratador',
                'tercero' => 'SUNAT_Envio_IndicadorPagadorFlete_Tercero',
            ];
            if (isset($map[$tipo]) && ! in_array($map[$tipo], $indicadores, true)) {
                $indicadores[] = $map[$tipo];
            }
        }
        if (! empty($data['datos_subcontratador']) && ! in_array('SUNAT_Envio_IndicadorTrasporteSubcontratado', $indicadores, true)) {
            $indicadores[] = 'SUNAT_Envio_IndicadorTrasporteSubcontratado';
        }

        if (! empty($indicadores)) {
            $shipment->setIndicadores($indicadores);
        }

        // Direcciones
        $llegada = new Direction($data['llegada_ubigeo'], $data['llegada_direccion']);
        if (! empty($data['llegada_ruc']))       $llegada->setRuc($data['llegada_ruc']);
        if (! empty($data['llegada_cod_local'])) $llegada->setCodLocal($data['llegada_cod_local']);

        $partida = new Direction($data['partida_ubigeo'], $data['partida_direccion']);
        if (! empty($data['partida_ruc']))       $partida->setRuc($data['partida_ruc']);
        if (! empty($data['partida_cod_local'])) $partida->setCodLocal($data['partida_cod_local']);

        $shipment->setLlegada($llegada);
        $shipment->setPartida($partida);

        // Transportista
        //   - GRR con modalidad 01: se informa al transportista contratado
        //   - GRT: la empresa del tenant ES el transportista; no duplicar aquí (ya está en DespatchSupplierParty)
        if ($tipoDocumento !== '31' && ! empty($data['transportista'])) {
            $shipment->setTransportista($this->buildTransportist($data['transportista']));
        }

        // Vehículo (transporte privado o GRT)
        if (! empty($data['vehiculo'])) {
            $shipment->setVehiculo($this->buildVehicle($data['vehiculo']));
        }

        // Conductores: aceptar objeto único o lista, por compatibilidad con
        // payloads que mandan "conductor": [{...}].
        $driversData = [];
        if (! empty($data['conductores'])) {
            $driversData = $data['conductores'];
        } elseif (! empty($data['conductor'])) {
            $driversData = array_is_list($data['conductor'])
                ? $data['conductor']
                : [$data['conductor']];
        }

        if (! empty($driversData)) {
            $drivers = array_map(fn ($c) => $this->buildDriver($c), $driversData);
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

        // TUC / Certificado de Habilitación Vehicular
        if (! empty($veh['nro_circulacion'])) {
            $vehicle->setNroCirculacion($veh['nro_circulacion']);
        } elseif (! empty($veh['tuc'])) {
            $vehicle->setNroCirculacion($veh['tuc']);
        }

        // Autorización especial (Cat D-37)
        if (! empty($veh['cod_emisor'])) {
            $vehicle->setCodEmisor($veh['cod_emisor']);
        }
        if (! empty($veh['nro_autorizacion'])) {
            $vehicle->setNroAutorizacion($veh['nro_autorizacion']);
        }

        // Vehículos secundarios (hasta 2)
        if (! empty($veh['secundarios'])) {
            $secundarios = array_map(function ($s) {
                $v = (new Vehicle())->setPlaca($s['placa']);
                if (! empty($s['nro_circulacion'])) $v->setNroCirculacion($s['nro_circulacion']);
                if (! empty($s['cod_emisor']))      $v->setCodEmisor($s['cod_emisor']);
                if (! empty($s['nro_autorizacion'])) $v->setNroAutorizacion($s['nro_autorizacion']);
                return $v;
            }, $veh['secundarios']);
            $vehicle->setSecundarios($secundarios);
        }

        return $vehicle;
    }

    private function buildDriver(array $cond): Driver
    {
        $driver = (new Driver())
            ->setTipoDoc($cond['tipo_doc'])
            ->setNroDoc($cond['num_doc'])
            ->setTipo($cond['tipo'] ?? 'Principal');

        if (! empty($cond['nombres']))   $driver->setNombres($cond['nombres']);
        if (! empty($cond['apellidos'])) $driver->setApellidos($cond['apellidos']);
        if (! empty($cond['licencia']))  $driver->setLicencia($cond['licencia']);

        return $driver;
    }

    private function buildCompany(): Company
    {
        return (new Company())
            ->setRuc($this->tenant->ruc)
            ->setRazonSocial($this->tenant->razon_social);
    }
}
