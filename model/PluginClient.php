<?php

declare(strict_types=1);

/**
 * Envia a mudança de status para o plugin do servidor de Minecraft.
 *
 * Contrato (documentado em README.md):
 *   POST {url}
 *   Authorization: Bearer {token}
 *   X-WrathSMP-Signature: hash_hmac sha256 do corpo JSON com o token
 *   Content-Type: application/json
 *
 *   { "action": "SET_VIP" | "REMOVE_VIP", "username": "...", "uuid": "...",
 *     "vip": true|false, "expires_at": "2026-12-19T12:00:00+00:00",
 *     "reason": "Pagamento 123" }
 *
 * Resposta esperada: HTTP 2xx com {"ok": true}.
 */
final class PluginClient
{
    private string $lastError = '';

    public function __construct(
        private string $url,
        private string $token,
        private int $timeout = 5
    ) {
    }

    public function getLastError(): string
    {
        return $this->lastError;
    }

    public function setVip(User $user, bool $active, ?DateTimeImmutable $expiresAt, string $reason): bool
    {
        $this->lastError = '';

        if ($this->url === '' || $this->token === '') {
            $this->lastError = 'URL ou token do plugin não configurados.';
            return false;
        }
        if (!function_exists('curl_init')) {
            $this->lastError = 'A extensão cURL do PHP é obrigatória.';
            return false;
        }

        $payload = [
            'action'     => $active ? 'SET_VIP' : 'REMOVE_VIP',
            'username'   => $user->getUsername(),
            'uuid'       => $user->getUuid(),
            'vip'        => $active,
            'expires_at' => $expiresAt?->format(DATE_ATOM),
            'reason'     => $reason,
        ];

        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            $this->lastError = 'Não foi possível serializar a requisição ao plugin.';
            return false;
        }

        $signature = hash_hmac('sha256', $json, $this->token);
        $ch = curl_init($this->url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $json,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'Accept: application/json',
                'Authorization: Bearer ' . $this->token,
                'X-WrathSMP-Signature: ' . $signature,
                'User-Agent: WrathSMP-VIP/1.0',
            ],
            CURLOPT_CONNECTTIMEOUT => 3,
            CURLOPT_TIMEOUT        => $this->timeout,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);

        $raw = curl_exec($ch);
        $error = curl_error($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($raw === false) {
            $this->lastError = 'Falha de conexão com o plugin: ' . $error;
            return false;
        }
        if ($status < 200 || $status >= 300) {
            $this->lastError = 'Plugin respondeu HTTP ' . $status . ': ' . substr((string) $raw, 0, 200);
            return false;
        }

        $decoded = json_decode((string) $raw, true);
        if (is_array($decoded) && array_key_exists('ok', $decoded) && $decoded['ok'] !== true) {
            $this->lastError = 'Plugin recusou a requisição: ' . (string) ($decoded['error'] ?? 'erro desconhecido');
            return false;
        }

        return true;
    }
}
