<?php

namespace App\Sire\Jobs;

use App\Sire\Enums\CodLibro;
use App\Sire\Enums\FasePeriodo;
use App\Sire\Models\SireComprobante;
use App\Sire\Models\SirePeriodo;
use App\Sire\Models\SireTicket;
use App\Sire\Services\Propuesta\PropuestaParser;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Parsea el archivo ZIP/TXT descargado de una propuesta RCE y persiste los
 * comprobantes en `sire_comprobantes` usando upsert por (tenant, periodo, car_sunat).
 *
 * Se dispara desde DownloadTicketFileJob cuando el archivo es de propuesta (codProceso 10).
 */
class ProcessPropuestaJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 30;
    public int $timeout = 300;

    public function __construct(public readonly int $ticketId)
    {
        $this->onQueue(config('sire.queues.process'));
    }

    public function handle(PropuestaParser $parser): void
    {
        /** @var SireTicket|null $ticket */
        $ticket = SireTicket::find($this->ticketId);

        if (! $ticket || ! $ticket->archivo_local_path) {
            Log::channel('stack')->warning('[SIRE][ProcessPropuestaJob] ticket sin archivo', [
                'ticket_id' => $this->ticketId,
            ]);
            return;
        }

        $disk = Storage::disk(config('sire.storage.disk'));
        if (! $disk->exists($ticket->archivo_local_path)) {
            Log::channel('stack')->error('[SIRE][ProcessPropuestaJob] archivo no existe', [
                'path' => $ticket->archivo_local_path,
            ]);
            return;
        }

        $absolutePath = $disk->path($ticket->archivo_local_path);
        $rows = $parser->parseZipFile($absolutePath);

        if (empty($rows)) {
            Log::channel('stack')->info('[SIRE][ProcessPropuestaJob] ZIP sin comprobantes', [
                'ticket' => $ticket->num_ticket,
            ]);
            return;
        }

        $periodo = SirePeriodo::firstOrCreate(
            [
                'tenant_id'      => $ticket->tenant_id,
                'per_tributario' => $ticket->per_tributario,
                'cod_libro'      => CodLibro::RCE->value,
            ],
            [
                'fase' => FasePeriodo::PROPUESTA->value,
            ],
        );

        DB::transaction(function () use ($rows, $ticket, $periodo) {
            $now = now();
            $inserted = 0;
            $updated = 0;

            foreach ($rows as $row) {
                // Sin CAR no podemos upsertar con unique — logueamos y saltamos
                if (empty($row['car_sunat'])) {
                    continue;
                }

                $existing = SireComprobante::query()
                    ->where('tenant_id', $ticket->tenant_id)
                    ->where('per_tributario', $ticket->per_tributario)
                    ->where('car_sunat', $row['car_sunat'])
                    ->first();

                $payload = [
                    'tenant_id'             => $ticket->tenant_id,
                    'sire_periodo_id'       => $periodo->id,
                    'origen_ticket_id'      => $ticket->id,
                    'per_tributario'        => $ticket->per_tributario,
                    'fase'                  => FasePeriodo::PROPUESTA->value,
                    'car_sunat'             => $row['car_sunat'],
                    'fec_emision'           => $row['fec_emision'] ?? $now->toDateString(),
                    'fec_vencimiento'       => $row['fec_vencimiento'] ?? null,
                    'cod_tipo_cdp'          => $row['cod_tipo_cdp'] ?? '00',
                    'num_serie_cdp'         => $row['num_serie_cdp'] ?? '',
                    'num_cdp'               => $row['num_cdp'] ?? '',
                    'tipo_doc_proveedor'    => $row['tipo_doc_proveedor'] ?? '6',
                    'num_doc_proveedor'    => $row['num_doc_proveedor'] ?? '',
                    'razon_social_proveedor'=> $row['razon_social_proveedor'] ?? '',
                    'cod_moneda'            => $row['cod_moneda'] ?? 'PEN',
                    'mto_bi_gravada'        => $row['mto_bi_gravada'] ?? 0,
                    'mto_igv'               => $row['mto_igv'] ?? 0,
                    'mto_bi_no_gravada'     => $row['mto_bi_no_gravada'] ?? 0,
                    'mto_total'             => $row['mto_total'] ?? 0,
                    'tipo_cambio'           => $row['tipo_cambio'] ?? null,
                    'raw_line'              => $row['raw_line'] ?? null,
                ];

                if ($existing) {
                    $existing->update($payload);
                    $updated++;
                } else {
                    SireComprobante::create($payload);
                    $inserted++;
                }
            }

            Log::channel('stack')->info('[SIRE][ProcessPropuestaJob] procesado', [
                'ticket'   => $ticket->num_ticket,
                'inserted' => $inserted,
                'updated'  => $updated,
            ]);
        });
    }
}
