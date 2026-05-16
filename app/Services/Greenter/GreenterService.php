<?php

namespace App\Services\Greenter;

use App\Models\Tenant;
use App\Services\Greenter\Builders\DespatchBuilder;
use App\Services\Greenter\Builders\InvoiceBuilder;
use App\Services\Greenter\Builders\NoteBuilder;
use App\Services\Greenter\Builders\SummaryBuilder;
use App\Services\Greenter\Builders\VoidedBuilder;
use Greenter\Factory\XmlBuilderResolver;
use Greenter\Model\DocumentInterface;
use Greenter\Model\Response\BillResult;
use Greenter\Model\Response\SummaryResult;
use Greenter\Model\Sale\Invoice;
use Greenter\Model\Sale\Note;
use Greenter\See;
use Greenter\Ws\Services\SunatEndpoints;
use Greenter\XMLSecLibs\Sunat\SignedXml;

class GreenterService
{
    private Tenant $tenant;
    private ?See $currentSee = null;
    private ?string $lastXml = null;

    public function __construct(Tenant $tenant)
    {
        $this->tenant = $tenant;
    }

    public function getLastXml(): ?string
    {
        return $this->lastXml ?? $this->currentSee?->getFactory()->getLastXml();
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

        // Credenciales OAuth2 GRE (requeridas para guías de remisión)
        $clientId = $this->tenant->client_id;
        $clientSecret = $this->tenant->client_secret;

        // En beta, usar credenciales de prueba de SUNAT si no están configuradas
        if ($env === 'beta' && (! $clientId || ! $clientSecret)) {
            $clientId = config('facturacion.sunat.beta.gre_client_id');
            $clientSecret = config('facturacion.sunat.beta.gre_client_secret');
        }

        if (! $clientId || ! $clientSecret) {
            throw new \RuntimeException(
                'Credenciales GRE (client_id/client_secret) no configuradas para el tenant ' . $this->tenant->ruc
                . '. Regístrelas en SUNAT → Clave SOL → Servicios en línea → API SUNAT.'
            );
        }

        $api->setApiCredentials($clientId, $clientSecret);

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

    /**
     * Envía una GRT (tipo 31) a SUNAT con flujo custom:
     *   1. Genera XML vía XmlBuilderResolver (no firmado)
     *   2. Inyecta cac:DespatchParty dentro de cac:Despatch (remitente)
     *      — Greenter no soporta este nodo nativamente para GRT
     *   3. Firma con SignedXml
     *   4. Envía via $api->sendXml()
     *
     * $data debe incluir:
     *   - 'remitente' => ['tipo_doc'=>'6','num_doc'=>'20XXXXXXXXX','razon_social'=>'...']
     */
    public function sendGrt(array $data): array
    {
        $despatch = $this->buildDespatch($data);

        // 1. XML no firmado
        $resolver = new \Greenter\Factory\XmlBuilderResolver([
            'strict_variables' => true,
            'optimizations' => 0,
            'debug' => false,
            'cache' => false,
        ]);
        $xmlBuilder = $resolver->find(get_class($despatch));
        $unsignedXml = $xmlBuilder->build($despatch);

        // 2. Inyectar DespatchParty (remitente) para GRT
        $unsignedXml = $this->injectDespatchPartyGrt($unsignedXml, $data['remitente'] ?? null);

        // 3. Firmar
        $cert = $this->tenant->getCertificateContent();
        if (! $cert) {
            throw new \RuntimeException('Certificado no encontrado para ' . $this->tenant->ruc);
        }
        $signer = new \Greenter\Xml\Signed\SignedXml();
        $signer->setCertificate($cert);
        $signedXml = $signer->signXml($unsignedXml);

        // 4. Enviar
        $api = $this->createApi();
        $result = $api->sendXml($despatch->getName(), $signedXml);

        return [
            'result' => $result,
            'xml' => $signedXml,
        ];
    }

    /**
     * Inyecta el bloque cac:DespatchParty dentro de cac:Despatch.
     *
     * Path SUNAT: /DespatchAdvice/cac:Shipment/cac:Delivery/cac:Despatch/cac:DespatchParty
     * Se inserta ANTES de cac:DespatchAddress (que Greenter sí emite).
     */
    private function injectDespatchPartyGrt(string $xml, ?array $remitente): string
    {
        if (empty($remitente) || empty($remitente['num_doc']) || empty($remitente['razon_social'])) {
            throw new \RuntimeException('GRT requiere los datos del remitente (tipo_doc, num_doc, razon_social).');
        }

        $tipoDoc = $remitente['tipo_doc'] ?? '6';
        $numDoc = htmlspecialchars($remitente['num_doc'], ENT_XML1);
        $razonSocial = htmlspecialchars($remitente['razon_social'], ENT_XML1);

        $block = '<cac:DespatchParty>'
            . '<cac:PartyIdentification>'
            . '<cbc:ID schemeID="' . $tipoDoc . '" schemeName="Documento de Identidad" '
            . 'schemeAgencyName="PE:SUNAT" schemeURI="urn:pe:gob:sunat:cpe:see:gem:catalogos:catalogo06">'
            . $numDoc
            . '</cbc:ID>'
            . '</cac:PartyIdentification>'
            . '<cac:PartyLegalEntity>'
            . '<cbc:RegistrationName><![CDATA[' . $razonSocial . ']]></cbc:RegistrationName>'
            . '</cac:PartyLegalEntity>'
            . '</cac:DespatchParty>';

        // Insertar antes de <cac:DespatchAddress> (primer ocurrencia dentro de cac:Despatch)
        $pattern = '/(<cac:Despatch>\s*)(<cac:DespatchAddress>)/';
        $replaced = preg_replace($pattern, '$1' . $block . '$2', $xml, 1, $count);
        if ($count === 0) {
            throw new \RuntimeException('No se encontró el nodo cac:Despatch > cac:DespatchAddress en el XML para inyectar DespatchParty.');
        }
        return $replaced;
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
        $this->currentSee = $see;

        // Build unsigned XML, strip languageLocaleID (SUNAT XSD rejects it), sign, then send.
        $cachePath = storage_path('app/cache/greenter');
        if (! is_dir($cachePath)) {
            mkdir($cachePath, 0755, true);
        }
        $options = ['autoescape' => false, 'cache' => $cachePath];
        $xmlBuilder = (new XmlBuilderResolver($options))->find(get_class($document));
        $unsignedXml = $xmlBuilder->build($document);
        $unsignedXml = (string) preg_replace('/ languageLocaleID="[^"]*"/', '', $unsignedXml);

        $cert = $this->tenant->getCertificateContent();
        $signer = new SignedXml();
        $signer->setCertificate($cert);
        $signedXml = $signer->signXml($unsignedXml);
        $this->lastXml = $signedXml;

        $result = $see->sendXmlFile($signedXml);
        $xml = $signedXml;

        // Verificar primero si es BillResult con CDR (incluye observaciones 3xxx)
        if ($result instanceof BillResult) {
            $cdr = $result->getCdrResponse();
            $cdrCode = $cdr ? (string) $cdr->getCode() : null;
            $isObservation = $cdrCode && str_starts_with($cdrCode, '3');

            if ($result->isSuccess() || $isObservation) {
                return [
                    'success' => true,
                    'xml' => $xml,
                    'cdr_zip' => $result->getCdrZip(),
                    'hash' => $this->extractHashFromXml($xml),
                    'code' => $cdrCode,
                    'description' => $cdr ? $this->sanitizeUtf8($cdr->getDescription()) : null,
                    'notes' => $cdr ? array_map(fn ($n) => $this->sanitizeUtf8($n), $cdr->getNotes() ?? []) : [],
                    'accepted' => $result->isSuccess() || $isObservation,
                ];
            }
        }

        if (! $result->isSuccess()) {
            $error = $result->getError();
            $errorCode = (string) $error->getCode();

            // Observaciones 3xxx: documento aceptado por SUNAT con advertencia
            // (aunque venga como SOAP fault, tratarlo como aceptado)
            if (str_starts_with($errorCode, '3')) {
                return [
                    'success' => true,
                    'xml' => $xml,
                    'cdr_zip' => $result instanceof BillResult ? $result->getCdrZip() : null,
                    'hash' => $this->extractHashFromXml($xml),
                    'code' => $errorCode,
                    'description' => 'Aceptado con observación',
                    'notes' => [$this->sanitizeUtf8($error->getMessage())],
                    'accepted' => true,
                ];
            }

            return [
                'success' => false,
                'xml' => $xml,
                'error_code' => $errorCode,
                'error_message' => $error->getMessage(),
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
                'error_message' => $this->sanitizeUtf8($error->getMessage()),
            ];
        }

        $cdr = $result->getCdrResponse();

        return [
            'success' => true,
            'cdr_zip' => $result->getCdrZip(),
            'code' => $cdr->getCode(),
            'description' => $this->sanitizeUtf8($cdr->getDescription()),
            'notes' => array_map(fn ($n) => $this->sanitizeUtf8($n), $cdr->getNotes() ?? []),
            'accepted' => $cdr->isAccepted(),
        ];
    }

    public function getGreStatus(string $ticket): array
    {
        $api = $this->createApi();

        try {
            $result = $api->getStatus($ticket);
        } catch (\Greenter\Sunat\GRE\ApiException $e) {
            return [
                'success' => false,
                'error_code' => (string) $e->getCode(),
                'error_message' => $this->sanitizeUtf8($e->getMessage()),
            ];
        }

        if (! $result->isSuccess()) {
            $error = $result->getError();

            return [
                'success' => false,
                'error_code' => (string) $error->getCode(),
                'error_message' => $this->sanitizeUtf8($error->getMessage()),
            ];
        }

        $cdr = $result->getCdrResponse();

        return [
            'success' => true,
            'cdr_zip' => $result->getCdrZip(),
            'code' => $cdr ? (string) $cdr->getCode() : null,
            'description' => $cdr ? $this->sanitizeUtf8($cdr->getDescription()) : null,
            'notes' => $cdr ? array_map(fn ($n) => $this->sanitizeUtf8($n), $cdr->getNotes() ?? []) : [],
            'accepted' => $cdr ? $cdr->isAccepted() : true,
        ];
    }

    public function getXmlSigned(DocumentInterface $document): string
    {
        $see = $this->resolveSee($document);

        return $see->getXmlSigned($document);
    }

    private function extractHashFromXml(?string $xml): ?string
    {
        if (empty($xml)) {
            return null;
        }

        try {
            $doc = new \DOMDocument();
            $doc->loadXML($xml);
            $xpath = new \DOMXPath($doc);
            $xpath->registerNamespace('ds', 'http://www.w3.org/2000/09/xmldsig#');
            $nodes = $xpath->query('//ds:Signature/ds:SignedInfo/ds:Reference/ds:DigestValue');

            if ($nodes && $nodes->length > 0) {
                return $nodes->item(0)->nodeValue;
            }
        } catch (\Throwable) {
            // fallback
        }

        return null;
    }

    private function sanitizeUtf8(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        // Intentar convertir desde ISO-8859-1 si no es UTF-8 válido
        if (! mb_check_encoding($value, 'UTF-8')) {
            $value = mb_convert_encoding($value, 'UTF-8', 'ISO-8859-1');
        }

        return $value;
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
