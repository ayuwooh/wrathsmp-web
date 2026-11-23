<?php

declare(strict_types=1);

/**
 * Cliente mínimo da API do MercadoPago (sem dependências externas).
 * Exige a extensão curl.
 */
final class MercadoPagoClient
{
    private const API = 'https://api.mercadopago.com';

    public function __construct(
        private string $accessToken,
        private array $mpConfig,
        private array $siteConfig
    ) {
        if ($this->accessToken === '') {
            throw new RuntimeException('MP_ACCESS_TOKEN não configurado.');
        }
        if (!function_exists('curl_init')) {
            throw new RuntimeException('A extensão cURL do PHP é obrigatória.');
        }
    }

    /**
     * Cria a preferência de pagamento e devolve a URL de checkout.
     * O external_reference carrega o id do usuário: é assim que o webhook
     * descobre para qual jogador dar o VIP.
     */
    public function createPreference(User $user): array
    {
        $days  = (int) $this->mpConfig['days'];
        $site  = rtrim($this->siteConfig['url'], '/');
        $page  = ltrim((string) $this->siteConfig['vip_page'], '/');

        $payload = [
            'items' => [[
                'id'          => 'vip-' . $days,
                'title'       => (string) $this->mpConfig['title'],
                'description' => sprintf('VIP de %d dias no WrathSMP - Jogador: %s', $days, $user->getUsername()),
                'quantity'    => 1,
                'currency_id' => (string) $this->mpConfig['currency'],
                'unit_price'  => (float) $this->mpConfig['price'],
            ]],
            'external_reference'  => (string) $user->getId(),
            'payer'               => ['email' => $user->getEmail()],
            'binary_mode'         => true, // só approved/rejected: ideal para produto digital
            'statement_descriptor'=> 'WRATHSMP VIP',
            'back_urls' => [
                'success' => "{$site}/{$page}?payment=approved",
                'pending' => "{$site}/{$page}?payment=pending",
                'failure' => "{$site}/{$page}?payment=rejected",
            ],
            'auto_return'       => 'approved',
            'notification_url'  => "{$site}/php/webhook.php",
        ];

        $response = $this->request('POST', self::API . '/checkout/preferences', $payload);

        $url = $this->mpConfig['sandbox']
            ? ($response['sandbox_init_point'] ?? null)
            : ($response['init_point'] ?? null);

        if (!is_string($url) || $url === '') {
            throw new RuntimeException('O MercadoPago não devolveu a URL de checkout.');
        }

        return [
            'id'           => (string) ($response['id'] ?? ''),
            'checkout_url' => $url,
        ];
    }

    /** Busca os dados completos de um pagamento pelo id recebido no webhook. */
    public function getPayment(string $id): array
    {
        if (!ctype_digit($id)) {
            throw new InvalidArgumentException('Id de pagamento inválido.');
        }
        return $this->request('GET', self::API . '/v1/payments/' . $id);
    }

    /**
     * Valida a assinatura x-signature do webhook.
     * Manifest atual: "id:[data.id];request-id:[x-request-id];ts:[ts];"
     * Também aceito o formato antigo sem request-id.
     */
    public static function verifyWebhookSignature(string $rawBody, array $headers, string $secret): bool
    {
        $signature = self::header($headers, 'x-signature');
        $requestId = self::header($headers, 'x-request-id');
        if ($signature === null || $signature === '') {
            return false;
        }

        if (!preg_match('/\bts=(\d+)/', $signature, $mTs) || !preg_match('/\bv1=([a-f0-9]+)/i', $signature, $mV1)) {
            return false;
        }

        $payload = json_decode($rawBody, true);
        $dataId = is_array($payload) ? (string) ($payload['data']['id'] ?? '') : '';
        $ts = $mTs[1];
        $expectedV1 = strtolower($mV1[1]);

        $manifests = [
            "id:{$dataId};request-id:{$requestId};ts:{$ts};",
            "id:{$dataId};ts:{$ts};",
        ];

        foreach ($manifests as $manifest) {
            if (hash_equals(hash_hmac('sha256', $manifest, $secret), $expectedV1)) {
                return true;
            }
        }
        return false;
    }

    /** Busca um header de forma case-insensitive. */
    private static function header(array $headers, string $name): ?string
    {
        foreach ($headers as $key => $value) {
            if (strtolower((string) $key) === $name) {
                return is_array($value) ? (string) reset($value) : (string) $value;
            }
        }
        return null;
    }

    private function request(string $method, string $url, ?array $body = null): array
    {
        $headers = [
            'Authorization: Bearer ' . $this->accessToken,
            'Content-Type: application/json',
            'Accept: application/json',
            'User-Agent: WrathSMP-VIP/1.0',
        ];

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        }

        $raw = curl_exec($ch);
        $error = curl_error($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($raw === false) {
            throw new RuntimeException('Falha de conexão com o MercadoPago: ' . $error);
        }

        $decoded = json_decode($raw, true);
        if ($status < 200 || $status >= 300 || !is_array($decoded)) {
            throw new RuntimeException(
                'MercadoPago respondeu ' . $status . ': ' . substr($raw, 0, 300)
            );
        }
        return $decoded;
    }
}
