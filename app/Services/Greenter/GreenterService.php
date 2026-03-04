<?php

namespace App\Services\Greenter;

use App\Models\Tenant;
use App\Services\Greenter\Builders\DespatchBuilder;
use App\Services\Greenter\Builders\InvoiceBuilder;
use App\Services\Greenter\Builders\NoteBuilder;
use App\Services\Greenter\Builders\SummaryBuilder;
use App\Services\Greenter\Builders\VoidedBuilder;
use Greenter\Model\DocumentInterface;
use Greenter\Model\Response\BillResult;
use Greenter\Model\Response\SummaryResult;
use Greenter\Model\Sale\Invoice;
use Greenter\Model\Sale\Note;
use Greenter\See;
use Greenter\Ws\Services\SunatEndpoints;

class GreenterService
{
    private Tenant $tenant;

    public function __construct(Tenant $tenant)
    {
        $this->tenant = $tenant;
    }

    public function createSee(?string $endpoint = null): See
    {
        $see = new See();

        if (! $endpoint) {
            $env = $this->tenant->environment;
            $endpoint = config("facturacion.sunat.{$env}.fe");
        }

        $see->setService($endpoint);

        $certificate = $this->tenant->getCertificateContent();
        if (! $certificate) {
            throw new \RuntimeException('Certificado digital no encontrado para el tenant ' . $this->tenant->ruc);
        }
        $see->setCertificate($certificate);

        $see->setClaveSOL(
            $this->tenant->ruc,
            $this->tenant->sol_user,
            $this->tenant->sol_pass
        );

        $cachePath = storage_path('app/cache/greenter');
        if (! is_dir($cachePath)) {
            mkdir($cachePath, 0755, true);
        }
        $see->setCachePath($cachePath);

        return $see;
    }

    public function createSeeRetention(): See
    {
        $env = $this->tenant->environment;

        return $this->createSee(config("facturacion.sunat.{$env}.retention"));
    }

    public function createApi(): \Greenter\Api
    {
        $env = $this->tenant->environment;

        $api = new \Greenter\Api([
            'auth' => config("facturacion.sunat.{$env}.guias_auth"),
            'cpe' => config("facturacion.sunat.{$env}.guias_cpe"),
        ]);

        $certificate = $this->tenant->getCertificateContent();
        if (! $certificate) {
            throw new \RuntimeException('Certificado digital no encontrado para el tenant ' . $this->tenant->ruc);
        }

        $api->setBuilderOptions([
            'strict_variables' => true,
            'optimizations' => 0,
            'debug' => false,
            'cache' => false,
        ]);

        $api->setClaveSOL(
            $this->tenant->ruc,
            $this->tenant->sol_user,
            $this->tenant->sol_pass
        );

        $api->setCertificate($certificate);

        return $api;
    }

    public function buildInvoice(array $data): Invoice
    {
        return (new InvoiceBuilder($this->tenant))->build($data);
    }

    public function buildNote(array $data): Note
    {
        return (new NoteBuilder($this->tenant))->build($data);
    }

    public function buildDespatch(array $data): \Greenter\Model\Despatch\Despatch
    {
        return (new DespatchBuilder($this->tenant))->build($data);
    }

    public function buildSummary(array $data): \Greenter\Model\Summary\Summary
    {
        return (new SummaryBuilder($this->tenant))->build($data);
    }

    public function buildVoided(array $data): \Greenter\Model\Voided\Voided
    {
        return (new VoidedBuilder($this->tenant))->build($data);
    }

    public function send(DocumentInterface $document): array
    {
        $see = $this->resolveSee($document);
        $result = $see->send($document);
        $xml = $see->getFactory()->getLastXml();

        if (! $result->isSuccess()) {
            $error = $result->getError();

            return [
                'success' => false,
                'xml' => $xml,
                'error_code' => $error->getCode(),
                'error_message' => $error->getMessage(),
            ];
        }

        if ($result instanceof BillResult) {
            $cdr = $result->getCdrResponse();

            return [
                'success' => true,
                'xml' => $xml,
                'cdr_zip' => $result->getCdrZip(),
                'hash' => $cdr->getId(),
                'code' => $cdr->getCode(),
                'description' => $cdr->getDescription(),
                'notes' => $cdr->getNotes(),
                'accepted' => $cdr->isAccepted(),
            ];
        }

        if ($result instanceof SummaryResult) {
            return [
                'success' => true,
                'xml' => $xml,
                'ticket' => $result->getTicket(),
            ];
        }

        return ['success' => false, 'xml' => $xml, 'error_message' => 'Tipo de respuesta no soportado'];
    }

    public function getStatus(string $ticket, ?string $endpoint = null): array
    {
        $see = $endpoint ? $this->createSee($endpoint) : $this->createSee();
        $result = $see->getStatus($ticket);

        if (! $result->isSuccess()) {
            $error = $result->getError();

            return [
                'success' => false,
                'error_code' => $error->getCode(),
                'error_message' => $error->getMessage(),
            ];
        }

        $cdr = $result->getCdrResponse();

        return [
            'success' => true,
            'cdr_zip' => $result->getCdrZip(),
            'code' => $cdr->getCode(),
            'description' => $cdr->getDescription(),
            'notes' => $cdr->getNotes(),
            'accepted' => $cdr->isAccepted(),
        ];
    }

    public function getXmlSigned(DocumentInterface $document): string
    {
        $see = $this->resolveSee($document);

        return $see->getXmlSigned($document);
    }

    private function resolveSee(DocumentInterface $document): See
    {
        $class = get_class($document);

        if (str_contains($class, 'Retention') || str_contains($class, 'Perception')) {
            return $this->createSeeRetention();
        }

        return $this->createSee();
    }
}
