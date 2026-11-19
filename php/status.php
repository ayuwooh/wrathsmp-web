<?php
/** GET status.php?username=Nick -> situação do VIP e dias restantes. */

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

$username = trim((string) ($_GET['username'] ?? ''));
if (!User::isValidUsername($username)) {
    Response::error('Nick inválido.', 422);
}

try {
    $config = config();
    $db = (new Database($config['db']))->pdo();
    $service = new VipService($db, $config);
    Response::ok($service->status($username));
} catch (Throwable $e) {
    error_log('[vip] erro ao consultar status: ' . $e->getMessage());
    Response::error('Não foi possível consultar o status agora.', 500);
}
