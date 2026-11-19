<?php
/**
 * Carregamento comum de todos os endpoints PHP.
 */

declare(strict_types=1);

date_default_timezone_set('UTC');

$GLOBALS['VIP_CONFIG'] = require __DIR__ . '/config.php';

/** Retorna o array de configuração carregado uma única vez. */
function config(): array
{
    return $GLOBALS['VIP_CONFIG'];
}

require_once __DIR__ . '/Response.php';
require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/User.php';
require_once __DIR__ . '/MercadoPagoClient.php';
require_once __DIR__ . '/PluginClient.php';
require_once __DIR__ . '/VipService.php';

// Qualquer erro não tratado vira JSON para o front-end (e 500 para o MercadoPago repetir o webhook).
set_exception_handler(static function (Throwable $e): void {
    error_log('[vip] ' . $e->getMessage() . ' em ' . $e->getFile() . ':' . $e->getLine());
    Response::json(['ok' => false, 'error' => 'Erro interno. Tente novamente mais tarde.'], 500);
});
