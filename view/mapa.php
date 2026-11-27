<?php
$base = '../';
$active = 'mapa';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Mapa interativo do WrathSMP: explore o mundo do servidor em tempo real.">
    <meta name="theme-color" content="#0E0F0F">
    <meta property="og:type" content="website">
    <meta property="og:locale" content="pt_BR">
    <meta property="og:title" content="WrathSMP - Mapa">
    <meta property="og:description" content="Explore o mundo do WrathSMP em tempo real.">
    <meta property="og:image" content="../assets/img/background.webp">
    <meta name="twitter:card" content="summary_large_image">
    <title>WrathSMP - Mapa</title>
    <link rel="icon" href="../assets/img/wrathsmp_icon.webp">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../css/style.css">
    <script src="../js/script.js" defer></script>
</head>
<body class="mapa-body">
<?php require __DIR__ . '/header.php'; ?>

    <main id="content">
        <h1 class="sr-only">Mapa do WrathSMP</h1>
        <iframe id="mapa-frame" src="http://wrathsmp.redmc.com.br:25608/"
                title="Mapa interativo do WrathSMP" allowfullscreen></iframe>
        <a class="mapa-open" href="http://wrathsmp.redmc.com.br:25608/" target="_blank" rel="noopener noreferrer">Abrir em nova aba</a>
    </main>

<?php require __DIR__ . '/footer.php'; ?>

</body>
</html>
