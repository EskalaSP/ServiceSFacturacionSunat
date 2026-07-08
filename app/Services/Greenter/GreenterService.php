<?php

namespace App\Services\Greenter;

use App\Models\Tenant;
use App\Services\Greenter\Builders\DespatchBuilder;
use App\Services\Greenter\Builders\InvoiceBuilder;
use App\Services\Greenter\Builders\NoteBuilder;
use App\Services\Greenter\Builders\SummaryBuilder;
use App\Services\Greenter\Builders\VoidedBuilder;
use Greenter\Api\ApiFactory;
use Greenter\Factory\XmlBuilderResolver;
use Greenter\Model\DocumentInterface;
use Greenter\Model\Response\BillResult;
use Greenter\Model\Response\SummaryResult;
use Greenter\Model\Sale\Invoice;
use Greenter\Model\Sale\Note;
use Greenter\See;
use Greenter\Sunat\GRE\Api\AuthApi;
use Greenter\Sunat\GRE\ApiException;
use Greenter\Sunat\GRE\Configuration;
use Greenter\Ws\Services\SunatEndpoints;
use Greenter\XMLSecLibs\Sunat\SignedXml;
use GuzzleHttp\Client;

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

        $see->setCertificate($this->resolveCertificatePem());

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
        $endpoints = [
            'auth' => config("facturacion.sunat.{$env}.guias_auth"),
            'cpe' => config("facturacion.sunat.{$env}.guias_cpe"),
        ];
        $client = new Client();
        $configuration = new Configuration();
        $factory = new ApiFactory(
            new AuthApi($client, $configuration->setHost($endpoints['auth'])),
            $client,
            new LaravelCacheTokenStore($this->greTokenNamespace()),
            $endpoints['cpe'],
        );

        $api = new \Greenter\Api($endpoints, $factory);

        $api->setBuilderOptions([
            'strict_variables' => true,
            'optimizations' => 0,
            'debug' => false,
            'cache' => false,
        ]);

        // Credenciales OAuth2 GRE (requeridas para guías de remisión).
        // Prioridad: per-tenant → global de config (SaaS: todos comparten la misma app SUNAT)
        $clientId     = $this->tenant->client_id     ?: config("facturacion.sunat.{$env}.gre_client_id");
        $clientSecret = $this->tenant->client_secret ?: config("facturacion.sunat.{$env}.gre_client_secret");

        if (! $clientId || ! $clientSecret) {
            throw new \RuntimeException(
                'Credenciales GRE (client_id/client_secret) no configuradas. '
                . 'Agrega SUNAT_GRE_CLIENT_ID y SUNAT_GRE_CLIENT_SECRET en .env'
            );
        }

        $api->setApiCredentials($clientId, $clientSecret);

        $api->setClaveSOL(
            $this->tenant->ruc,
            $this->tenant->sol_user,
            $this->tenant->sol_pass
        );

        $api->setCertificate($this->resolveCertificatePem());

        return $api;
    }

    private function greTokenNamespace(): string
    {
        $env = $this->tenant->environment;

        return implode('|', [
            $env,
            config("facturacion.sunat.{$env}.guias_auth"),
            config("facturacion.sunat.{$env}.guias_cpe"),
            $this->tenant->ruc,
            $this->tenant->sol_user,
        ]);
    }

    private function greClientId(): ?string
    {
        $env = $this->tenant->environment;

        return $this->tenant->client_id ?: config("facturacion.sunat.{$env}.gre_client_id");
    }

    private function forgetGreToken(): void
    {
        (new LaravelCacheTokenStore($this->greTokenNamespace()))->forget($this->greClientId());
    }

    private function isMissingGreTokenError(\Throwable $e): bool
    {
        return $e instanceof ApiException
            && (int) $e->getCode() === 401
            && str_contains($e->getMessage(), 'Token NO existe');
    }

    private function isMissingGreTokenResult(mixed $result): bool
    {
        if (! is_object($result) || ! method_exists($result, 'isSuccess') || $result->isSuccess()) {
            return false;
        }

        if (! method_exists($result, 'getError')) {
            return false;
        }

        $error = $result->getError();

        return $error
            && (string) $error->getCode() === 'API'
            && str_contains((string) $error->getMessage(), 'Token NO existe');
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
        return $this->sendDespatch($data);
    }

    /**
     * Envía una GRE con ajustes XML que Greenter aún no modela en su API pública.
     *
     * GRT (tipo 31):
     *   - Inyecta cac:DespatchParty dentro de cac:Despatch.
     *
     * @return array{result: mixed, xml: string}
     */
    public function sendDespatch(array $data): array
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

        $tipoDocumento = $data['tipo_documento'] ?? '09';
        $modTraslado = $data['mod_traslado'] ?? null;

        // 2. Inyectar nodos requeridos por SUNAT no cubiertos por Greenter.
        if ($tipoDocumento === '09' && $modTraslado === '01' && ! empty($data['fecha_entrega_transportista'])) {
            $unsignedXml = $this->injectCarrierDeliveryDate(
                $unsignedXml,
                $data['fecha_entrega_transportista']
            );
        }

        if ($tipoDocumento === '31') {
            $unsignedXml = $this->injectDespatchPartyGrt($unsignedXml, $data['remitente'] ?? null);
        }

        // 3. Firmar después de toda modificación del XML.
        $signer = new SignedXml();
        $signer->setCertificate($this->resolveCertificatePem());
        $signedXml = $signer->signXml($unsignedXml);

        // 4. Enviar. Nubefact beta puede invalidar tokens del cache; reintentar
        // una vez con token fresco cuando responde "Token NO existe".
        try {
            $api = $this->createApi();
            $result = $api->sendXml($despatch->getName(), $signedXml);
        } catch (\Throwable $e) {
            if (! $this->isMissingGreTokenError($e)) {
                throw $e;
            }

            $this->forgetGreToken();
            $api = $this->createApi();
            $result = $api->sendXml($despatch->getName(), $signedXml);
        }

        if ($this->isMissingGreTokenResult($result)) {
            $this->forgetGreToken();
            $api = $this->createApi();
            $result = $api->sendXml($despatch->getName(), $signedXml);
        }

        return [
            'result' => $result,
            'xml' => $signedXml,
        ];
    }

    /**
     * SUNAT/Nubefact exige desde 2026-06-01 la fecha de entrega de bienes al
     * transportista para GRE remitente con transporte publico.
     *
     * Greenter 5.3.0 modela este campo como envio.fecEntregaBienes y lo emite
     * en cac:LoadingTransportEvent/cbc:OccurrenceDate. La version instalada
     * aun no tiene ese setter, por eso se inyecta antes de firmar.
     */
    private function injectCarrierDeliveryDate(string $xml, string $date): string
    {
        $deliveryDate = (new \DateTime($date))->format('Y-m-d');
        $block = '<cac:LoadingTransportEvent>'
            . '<cbc:OccurrenceDate>' . $deliveryDate . '</cbc:OccurrenceDate>'
            . '</cac:LoadingTransportEvent>';

        $pattern = '/(<\/cac:CarrierParty>)/';
        $replaced = preg_replace($pattern, '$1' . $block, $xml, 1, $count);

        if ($count === 0) {
            throw new \RuntimeException('No se encontró cac:CarrierParty para inyectar fecha_entrega_transportista.');
        }

        return $replaced;
    }

    /**
     * Inyecta el bloque cac:DespatchParty dentro de cac:Despatch.
     *
     * Path SUNAT: /DespatchAdvice/cac:Shipment/cac:Delivery/cac:Despatch/cac:DespatchParty
     * En UBL cac:DespatchAddress debe ir antes de cac:DespatchParty.
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

        // Insertar despues de </cac:DespatchAddress> (primer ocurrencia dentro de cac:Despatch).
        $pattern = '/(<cac:Despatch>\s*<cac:DespatchAddress>.*?<\/cac:DespatchAddress>)/s';
        $replaced = preg_replace($pattern, '$1' . $block, $xml, 1, $count);
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

        // Build unsigned XML, sign, then send.
        $cachePath = storage_path('app/cache/greenter');
        if (! is_dir($cachePath)) {
            mkdir($cachePath, 0755, true);
        }
        $options = ['autoescape' => false, 'cache' => $cachePath];
        $xmlBuilder = (new XmlBuilderResolver($options))->find(get_class($document));
        $unsignedXml = $xmlBuilder->build($document);

        $cert = $this->resolveCertificatePem();
        $signer = new SignedXml();
        $signer->setCertificate($cert);
        $signedXml = $signer->signXml($unsignedXml);
        $this->lastXml = $signedXml;

        $result = $see->sendXml(get_class($document), $document->getName(), $signedXml);
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

        try {
            $result = $see->getStatus($ticket);
        } catch (\SoapFault $e) {
            return [
                'success'       => false,
                'error_code'    => 'NETWORK_ERROR',
                'error_message' => $this->sanitizeUtf8($e->getMessage()),
            ];
        }

        if (! $result->isSuccess()) {
            $error = $result->getError();
            $code  = (string) $error->getCode();
            $msg   = $this->sanitizeUtf8($error->getMessage());

            // Greenter wraps network-layer failures (SoapFaults caught internally) as
            // numeric codes like 200 with messages such as "Failed to process response
            // headers". Those are transient, not SUNAT validation rejections.
            $looksLikeNetworkError = ! is_numeric($code) ||
                str_contains(strtolower($msg), 'failed to process') ||
                str_contains(strtolower($msg), 'could not connect') ||
                str_contains(strtolower($msg), 'connection refused') ||
                str_contains(strtolower($msg), 'timed out');

            if ($looksLikeNetworkError) {
                $code = 'NETWORK_ERROR';
            }

            return [
                'success'       => false,
                'error_code'    => $code,
                'error_message' => $msg,
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
        } catch (ApiException $e) {
            if ($this->isMissingGreTokenError($e)) {
                try {
                    $this->forgetGreToken();
                    $api = $this->createApi();
                    $result = $api->getStatus($ticket);
                } catch (ApiException $retryException) {
                    return [
                        'success' => false,
                        'error_code' => (string) $retryException->getCode(),
                        'error_message' => $this->sanitizeUtf8($retryException->getMessage()),
                    ];
                }
            } else {
                return [
                    'success' => false,
                    'error_code' => (string) $e->getCode(),
                    'error_message' => $this->sanitizeUtf8($e->getMessage()),
                ];
            }
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'error_code' => (string) $e->getCode(),
                'error_message' => $this->sanitizeUtf8($e->getMessage()),
            ];
        }

        if ($this->isMissingGreTokenResult($result)) {
            $this->forgetGreToken();
            $api = $this->createApi();
            $result = $api->getStatus($ticket);
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
            // valor de respaldo
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

    /**
     * Devuelve el certificado en formato PEM para firmar XMLs.
     * Prioridad: (1) Certificado PSE global (.env CERTIFICATE_PATH)
     *            (2) Certificado individual del tenant
     * Convierte automáticamente de .p12/.pfx a PEM si es necesario.
     */
    private function resolveCertificatePem(): string
    {
        // 0. Base64 en env var (ideal para Railway/cloud sin volúmenes de archivos)
        $pemB64 = config('facturacion.certificate.pem_b64', '');
        if (! empty($pemB64)) {
            $content = base64_decode($pemB64, strict: true);
            if ($content !== false && ! empty($content)) {
                return $this->toPem($content);
            }
        }

        // 1. Certificado PSE global como archivo (desarrollo local y servidores con storage)
        $psePath = config('facturacion.certificate.path', '');
        if (! empty($psePath)) {
            $fullPath = str_starts_with($psePath, '/') || str_contains($psePath, ':')
                ? $psePath
                : base_path($psePath);

            if (file_exists($fullPath)) {
                $content = file_get_contents($fullPath);
                return $this->toPem($content);
            }
        }

        // 2. Certificado individual del tenant
        $tenantCert = $this->tenant->getCertificateContent();
        if ($tenantCert) {
            return $this->toPem($tenantCert, $this->tenant->certificate_password);
        }

        throw new \RuntimeException(
            'Certificado digital no encontrado. '
            . 'Configure CERTIFICATE_PEM_B64 (Railway) o CERTIFICATE_PATH en .env.'
        );
    }

    /**
     * Convierte contenido de certificado a PEM si es un archivo .p12/.pfx binario.
     * Si ya está en PEM (empieza con -----) lo devuelve sin cambios.
     */
    private function toPem(string $content, ?string $tenantPassword = null): string
    {
        \Illuminate\Support\Facades\Log::info("toPem called for tenant: " . ($this->tenant->ruc ?? 'unknown'), [
            'tenant_password' => $tenantPassword,
            'content_len' => strlen($content)
        ]);

        if (str_starts_with(ltrim($content), '-----')) {
            return $content;
        }

        $certs = [];
        $password = (string) config('facturacion.certificate.password', '');

        // Intentar con la contraseña configurada, la del tenant, la del RUC PSE, y finalmente vacío
        $attempts = array_filter(array_unique([
            $password,
            $tenantPassword,
            (string) config('facturacion.certificate.pse_ruc', ''),
            ''
        ]), fn($val) => $val !== null && $val !== '');

        \Illuminate\Support\Facades\Log::info("toPem attempts prepared", [
            'attempts' => $attempts
        ]);

        $success = false;
        foreach ($attempts as $pass) {
            if (@openssl_pkcs12_read($content, $certs, $pass) && ! empty($certs['pkey'])) {
                \Illuminate\Support\Facades\Log::info("toPem successfully decrypted certificate with password: " . $pass);
                $success = true;
                break;
            }
        }

        if (!$success) {
            \Illuminate\Support\Facades\Log::info("toPem fallback to empty password");
            if (@openssl_pkcs12_read($content, $certs, '') && ! empty($certs['pkey'])) {
                \Illuminate\Support\Facades\Log::info("toPem successfully decrypted certificate with empty password");
                $success = true;
            }
        }

        if (empty($certs) || empty($certs['pkey']) || empty($certs['cert'])) {
            $error = openssl_error_string() ?: 'Error desconocido';
            \Illuminate\Support\Facades\Log::error("toPem failed to decrypt PKCS12", [
                'error' => $error
            ]);
            throw new \RuntimeException(
                "No se pudo leer el certificado .p12. Verifica CERTIFICATE_PASSWORD en .env. OpenSSL: {$error}"
            );
        }

        // SignedXml::setCertificate() espera: clave privada PEM + certificado PEM concatenados
        return $certs['pkey'] . $certs['cert'];
    }
}
