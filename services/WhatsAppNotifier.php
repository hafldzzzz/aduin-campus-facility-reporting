<?php
declare(strict_types=1);

/** The Cloud API requires international digits; keep one canonical Indonesian number. */
function normalizeWhatsAppRecipient(string $number): ?string
{
    $number = trim($number);
    if ($number === '' || preg_match('/[^0-9+() .-]/', $number)) {
        return null;
    }
    $number = preg_replace('/[() .-]/', '', $number);
    if (str_starts_with($number, '+')) {
        $number = substr($number, 1);
    }
    if (str_starts_with($number, '08')) {
        $number = '62' . substr($number, 1);
    }
    return preg_match('/^628[0-9]{8,11}$/D', $number) ? $number : null;
}

function whatsAppConfig(): array
{
    $defaults = [
        'WA_ENABLED' => false,
        'WA_API_VERSION' => '',
        'WA_PHONE_NUMBER_ID' => '',
        'WA_ACCESS_TOKEN' => '',
        'WA_TEMPLATE_NAME' => 'aduan_masuk_admin',
        'WA_TEMPLATE_LANG' => 'id',
        'WA_CONNECT_TIMEOUT' => 5,
        'WA_TIMEOUT' => 20,
        'WA_MAX_ATTEMPTS' => 5,
    ];
    foreach ($defaults as $name => $default) {
        $defaults[$name] = appConfig($name, $default);
    }
    $defaults['WA_ENABLED'] = filter_var($defaults['WA_ENABLED'], FILTER_VALIDATE_BOOLEAN);
    return $defaults;
}

function whatsAppRetryDelay(int $attempt): int
{
    return min(3600, 60 * (2 ** min(6, max(0, $attempt - 1))));
}

function whatsAppExceptionCode(Throwable $exception): string
{
    $message = $exception->getMessage();
    return preg_match('/^whatsapp_[a-z_]{1,70}$/D', $message) ? $message : 'whatsapp_internal_error';
}

final class WhatsAppNotifier
{
    private array $config;
    private $transport;

    /** A replacement transport is only permitted in an explicitly selected test environment. */
    public function __construct(array $config, ?callable $transport = null, bool $allowTestTransport = false)
    {
        if ($transport !== null && (!$allowTestTransport || appConfig('APP_ENV', 'production') !== 'test')) {
            throw new LogicException('whatsapp_test_transport_forbidden');
        }
        $this->config = $config;
        $this->transport = $transport;
    }

    public function assertReady(): void
    {
        if (!filter_var($this->config['WA_ENABLED'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
            throw new RuntimeException('whatsapp_disabled');
        }
        if (!preg_match('/^v[0-9]{1,3}\.[0-9]{1,2}$/D', (string)($this->config['WA_API_VERSION'] ?? ''))) {
            throw new RuntimeException('whatsapp_api_version_required');
        }
        if (!preg_match('/^[0-9]{5,40}$/D', (string)($this->config['WA_PHONE_NUMBER_ID'] ?? ''))) {
            throw new RuntimeException('whatsapp_phone_number_id_required');
        }
        $token = (string)($this->config['WA_ACCESS_TOKEN'] ?? '');
        if ($token === '' || strlen($token) > 4096 || preg_match('/[\x00-\x20\x7f]/', $token)) {
            throw new RuntimeException('whatsapp_access_token_required');
        }
        if ($this->transport === null && !extension_loaded('curl')) {
            throw new RuntimeException('whatsapp_curl_extension_required');
        }
    }

    public function buildPayload(array $job): array
    {
        $recipient = normalizeWhatsAppRecipient((string)($job['recipient'] ?? ''));
        $template = (string)($job['template_name'] ?? '');
        $language = (string)($job['template_lang'] ?? '');
        if ($recipient === null || !preg_match('/^[a-z0-9_]{1,128}$/D', $template)
            || !preg_match('/^[a-z]{2}(?:_[A-Z]{2})?$/D', $language)) {
            throw new RuntimeException('whatsapp_invalid_job');
        }
        $data = json_decode((string)($job['payload'] ?? ''), true, 8, JSON_THROW_ON_ERROR);
        if (!is_array($data) || count($data) !== 4) {
            throw new RuntimeException('whatsapp_invalid_payload');
        }
        $parameters = [];
        foreach (['no_laporan', 'nama_kategori', 'lokasi', 'status'] as $name) {
            $value = $data[$name] ?? null;
            if (!is_string($value) || trim($value) === '' || strlen($value) > 320
                || preg_match('/[\x00-\x1f\x7f]/', $value)) {
                throw new RuntimeException('whatsapp_invalid_payload');
            }
            $parameters[] = ['type' => 'text', 'text' => $value];
        }
        return [
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => $recipient,
            'type' => 'template',
            'template' => [
                'name' => $template,
                'language' => ['code' => $language],
                'components' => [['type' => 'body', 'parameters' => $parameters]],
            ],
        ];
    }

    public function send(array $job): array
    {
        $this->assertReady();
        $payload = json_encode($this->buildPayload($job), JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        $url = 'https://graph.facebook.com/' . $this->config['WA_API_VERSION'] . '/'
            . $this->config['WA_PHONE_NUMBER_ID'] . '/messages';
        $headers = ['Authorization: Bearer ' . $this->config['WA_ACCESS_TOKEN'], 'Content-Type: application/json'];
        $connectTimeout = max(1, min(10, (int)($this->config['WA_CONNECT_TIMEOUT'] ?? 5)));
        $timeout = max($connectTimeout + 1, min(60, (int)($this->config['WA_TIMEOUT'] ?? 20)));
        if ($this->transport !== null) {
            $response = ($this->transport)($url, $headers, $payload, [
                'connect_timeout' => $connectTimeout, 'timeout' => $timeout, 'verify_tls' => true,
            ]);
            return self::classifyResponse((int)($response['http_status'] ?? 0), (string)($response['body'] ?? ''), (int)($response['curl_errno'] ?? 0));
        }
        $body = '';
        $handle = curl_init($url);
        if ($handle === false) {
            return ['status' => 'pending', 'provider_id' => null, 'error_code' => 'curl_initialization_failed'];
        }
        curl_setopt_array($handle, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_CONNECTTIMEOUT => $connectTimeout,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_NOSIGNAL => true,
            CURLOPT_WRITEFUNCTION => static function ($handle, string $chunk) use (&$body): int {
                if (strlen($body) + strlen($chunk) > 65536) {
                    return 0;
                }
                $body .= $chunk;
                return strlen($chunk);
            },
        ]);
        curl_exec($handle);
        $errno = curl_errno($handle);
        $status = (int)curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        curl_close($handle);
        return self::classifyResponse($status, $body, $errno);
    }

    /** Never infer delivery or blindly retry a request whose outcome may be unknown. */
    public static function classifyResponse(int $httpStatus, string $body, int $curlErrno = 0): array
    {
        $result = ['status' => 'uncertain', 'provider_id' => null, 'error_code' => 'unknown_provider_outcome'];
        if ($curlErrno !== 0) {
            // DNS/connect/TLS failures happen before the HTTP message can be submitted.
            $result['status'] = in_array($curlErrno, [5, 6, 7, 35, 51, 58, 60, 77, 90], true) ? 'pending' : 'uncertain';
            $result['error_code'] = 'curl_' . $curlErrno;
            return $result;
        }
        $data = json_decode($body, true, 16);
        $providerId = is_array($data) ? ($data['messages'][0]['id'] ?? null) : null;
        if ($httpStatus >= 200 && $httpStatus < 300 && is_string($providerId)
            && preg_match('/^[A-Za-z0-9._=:+\/-]{1,255}$/D', $providerId)) {
            return ['status' => 'accepted', 'provider_id' => $providerId, 'error_code' => null];
        }
        $error = is_array($data) ? ($data['error'] ?? null) : null;
        if ($httpStatus >= 400 && $httpStatus < 500 && is_array($error)
            && isset($error['code']) && is_numeric($error['code'])) {
            $result['status'] = $httpStatus === 429 ? 'pending' : 'failed';
            $result['error_code'] = 'meta_http_' . $httpStatus . '_code_' . (int)$error['code'];
        } elseif ($httpStatus !== 0) {
            $result['error_code'] = 'meta_http_' . $httpStatus . '_unknown_outcome';
        }
        return $result;
    }
}
