<?php
/**
 * Configurações do sistema VIP.
 *
 * As credenciais NÃO ficam no código: use variáveis de ambiente
 * (painel da hospedagem, .htaccess SetEnv, systemd, export, etc).
 * Veja php/README.md para a lista completa e um exemplo.
 */

if (!function_exists('env')) {
    function env(string $key, string $default = ''): string
    {
        $value = getenv($key);
        return $value === false ? $default : $value;
    }
}

return [
    'db' => [
        'host' => env('DB_HOST', 'localhost'),
        'port' => env('DB_PORT', '3306'),
        'name' => env('DB_NAME', 'wrathsmp'),
        'user' => env('DB_USER', 'root'),
        'pass' => env('DB_PASS', ''),
    ],

    'site' => [
        // URL pública do site, sem barra no final. Usada nos back_urls e no webhook.
        'url' => rtrim(env('SITE_URL', 'http://localhost/wrathsmp-web'), '/'),
        'vip_page' => 'view/vip.html',
    ],

    'mercadopago' => [
        'access_token'   => env('MP_ACCESS_TOKEN'),
        'webhook_secret' => env('MP_WEBHOOK_SECRET'),
        // true = sandbox_init_point (teste); false = init_point (produção)
        'sandbox' => filter_var(env('MP_SANDBOX', 'true'), FILTER_VALIDATE_BOOL),
        'price'   => (float) env('VIP_PRICE', '15.00'),
        'days'    => max(1, (int) env('VIP_DAYS', '30')),
        'currency' => env('VIP_CURRENCY', 'BRL'),
        'title'    => env('VIP_TITLE', 'VIP WrathSMP - 30 dias'),
    ],

    'plugin' => [
        // Endpoint HTTP exposto pelo plugin de Minecraft (contract em php/README.md).
        'url'     => env('VIP_PLUGIN_URL'),
        'token'   => env('VIP_PLUGIN_TOKEN'),
        'timeout' => max(1, (int) env('VIP_PLUGIN_TIMEOUT', '5')),
    ],

    'cron' => [
        // Token para chamar expire.php por HTTP. Deixe vazio para usar apenas CLI.
        'token' => env('VIP_CRON_TOKEN'),
    ],
];
