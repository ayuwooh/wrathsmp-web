<?php
/**
 * Manutenção periódica (execute a cada 5 ou 10 minutos):
 *  1. remove no servidor o VIP de quem já passou da data;
 *  2. reenvia avisos ao plugin que falharam no webhook.
 *
 * CLI:   php php/expire.php
 * HTTP:  curl -H "X-Cron-Token: SEU_TOKEN" https://seusite/php/expire.php
 */

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

$config = config();

if (PHP_SAPI !== 'cli') {
    $token = '';
    foreach ($_SERVER as $key => $value) {
        if (strtolower((string) $key) === 'http_x_cron_token') {
            $token = (string) $value;
            break;
        }
    }
    $expected = (string) $config['cron']['token'];
    if ($expected === '' || $token === '' || !hash_equals($expected, $token)) {
        Response::error('Não autorizado.', 401);
    }
}

try {
    $db = (new Database($config['db']))->pdo();
    $service = new VipService($db, $config);

    $result = [
        'expired' => $service->revokeExpiredUsers(),
        'notifications' => $service->retryFailedNotifications(),
        'checked_at' => gmdate(DATE_ATOM),
    ];

    if (PHP_SAPI === 'cli') {
        echo json_encode($result, JSON_UNESCAPED_UNICODE) . PHP_EOL;
        exit(0);
    }
    Response::ok($result);
} catch (Throwable $e) {
    error_log('[vip] falha na manutenção: ' . $e->getMessage());
    if (PHP_SAPI === 'cli') {
        fwrite(STDERR, $e->getMessage() . PHP_EOL);
        exit(1);
    }
    Response::error('Falha na manutenção.', 500);
}
