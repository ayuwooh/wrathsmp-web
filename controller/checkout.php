<?php
/**
 * POST { username, email } -> cria a preferência no MercadoPago e devolve a URL.
 * O VIP só é ativado depois que o webhook confirmar o pagamento.
 */

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::error('Método não permitido.', 405);
}

$input = json_decode(file_get_contents('php://input') ?: '', true);
if (!is_array($input)) {
    Response::error('Requisição inválida.', 400);
}

$username = trim((string) ($input['username'] ?? ''));
$email    = trim((string) ($input['email'] ?? ''));

if (!User::isValidUsername($username)) {
    Response::error('Digite um nick válido (3 a 16 letras, números ou _).', 422);
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    Response::error('Digite um e-mail válido.', 422);
}

$config = config();
if ($config['mercadopago']['access_token'] === '') {
    Response::error('Pagamento indisponível no momento.', 503);
}

try {
    $db = (new Database($config['db']))->pdo();
    $service = new VipService($db, $config);
    $user = $service->findOrCreateUser($username, $email);

    $mercadoPago = new MercadoPagoClient(
        (string) $config['mercadopago']['access_token'],
        $config['mercadopago'],
        $config['site']
    );
    $checkout = $mercadoPago->createPreference($user);

    Response::ok([
        'checkout_url'  => $checkout['checkout_url'],
        'preference_id' => $checkout['id'],
        'username'      => $user->getUsername(),
    ]);
} catch (Throwable $e) {
    error_log('[vip] erro no checkout: ' . $e->getMessage());
    Response::error('Não foi possível iniciar o pagamento. Tente novamente em instantes.', 502);
}
