<?php
/** Preço e duração atuais (o front-end lê daqui para não ficar desatualizado). */

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

$mp = config()['mercadopago'];

Response::ok([
    'price'       => (float) $mp['price'],
    'price_label' => 'R$ ' . number_format((float) $mp['price'], 2, ',', '.'),
    'currency'    => (string) $mp['currency'],
    'days'        => (int) $mp['days'],
    'title'       => (string) $mp['title'],
]);
