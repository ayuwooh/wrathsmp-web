<?php
/**
 * Endpoint de notificação do MercadoPago.
 *
 * Configurar no painel: URL de notificação = https://seusite/php/webhook.php
 * e gerar um secret de webhook para MP_WEBHOOK_SECRET.
 * Responder 500 quando falhar faz o MercadoPago tentar de novo.
 */

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

$raw = file_get_contents('php://input') ?: '';
$config = config();
$secret = (string) $config['mercadopago']['webhook_secret'];

// $_SERVER guarda headers como HTTP_X_SIGNATURE; normalizamos para x-signature.
$headers = [];
foreach ($_SERVER as $key => $value) {
    if (str_starts_with((string) $key, 'HTTP_')) {
        $name = strtolower(str_replace('_', '-', substr((string) $key, 5)));
        $headers[$name] = $value;
    }
}

if ($secret !== '' && !MercadoPagoClient::verifyWebhookSignature($raw, $headers, $secret)) {
    error_log('[vip] webhook com assinatura inválida');
    Response::error('Assinatura inválida.', 401);
}

$payload = json_decode($raw, true);
if (!is_array($payload)) {
    Response::error('JSON inválido.', 400);
}

$type = (string) ($payload['type'] ?? ($payload['data']['type'] ?? ''));
$paymentId = (string) ($payload['data']['id'] ?? ($payload['id'] ?? ''));

// Outras notificações (ex.: merchant_order) são ignoradas com 200.
if ($type !== 'payment' || $paymentId === '') {
    Response::ok(['ignored' => true]);
}

try {
    $mercadoPago = new MercadoPagoClient(
        (string) $config['mercadopago']['access_token'],
        $config['mercadopago'],
        $config['site']
    );
    $payment = $mercadoPago->getPayment($paymentId);

    $db = (new Database($config['db']))->pdo();
    $service = new VipService($db, $config);
    $service->processPayment($payment);

    Response::ok(['processed' => true, 'status' => $payment['status'] ?? null]);
} catch (Throwable $e) {
    error_log('[vip] falha no webhook: ' . $e->getMessage());
    Response::error('Falha ao processar a notificação.', 500);
}
