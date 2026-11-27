<?php
/**
 * Cabeçalho compartilhado por todas as páginas.
 *
 * Variáveis esperadas (definidas pela página antes do include):
 * - $base: '' na raiz do site, '../' nas páginas dentro de view/
 * - $active: home | wiki | changelog | vip | mapa | suporte
 * - $skipTarget: alvo do link "Pular para o conteúdo" (padrão: #content)
 */
$base = $base ?? '';
$active = $active ?? '';
$skipTarget = $skipTarget ?? '#content';

$nav = [
    'home'      => [$base . 'index.php', 'Home'],
    'wiki'      => [$base . 'view/wiki.php', 'Wiki'],
    'changelog' => [$base . 'view/changelog.php', 'Atualizações'],
    'vip'       => [$base . 'view/vip.php', 'VIP'],
    'mapa'      => [$base . 'view/mapa.php', 'Mapa'],
    'suporte'   => [$base . 'view/suporte.php', 'Suporte'],
];
?>
    <!-- Fica escondido até receber foco pelo teclado (Tab) -->
    <a class="skip-link" href="<?= htmlspecialchars($skipTarget, ENT_QUOTES, 'UTF-8') ?>">Pular para o conteúdo</a>

    <!-- Os ids do cabeçalho (top, logo, topbar, ips) são usados pelo CSS -->
    <header id="top">
        <img src="<?= $base ?>assets/img/wrathsmp_logo_W.webp" alt="WrathSMP" id="logo">

        <!-- Menu único: para adicionar uma página, basta incluí-la em $nav acima -->
        <nav id="topbar" aria-label="Navegação principal">
<?php foreach ($nav as $key => [$path, $label]): ?>
            <a href="<?= $path ?>"<?= $key === $active ? ' aria-current="page"' : '' ?>><?= $label ?></a>
<?php endforeach; ?>
        </nav>

        <!-- IPs do servidor: clique em um deles para copiar -->
        <div id="ips">
            <dl>
                <dt>IP Java</dt>
                <dd><button type="button" class="ip-copy" data-tooltip="Clique para copiar" aria-label="Copiar IP Java"><code>wrathsmp.redmc.com.br</code></button></dd>
                <dt>IP Bedrock</dt>
                <dd><button type="button" class="ip-copy" data-tooltip="Clique para copiar" aria-label="Copiar IP Bedrock"><code>wrathsmpbr.redmc.com.br:25587</code></button></dd>
            </dl>
            <!-- Aviso temporário após copiar (o JS preenche e limpa este texto) -->
            <p id="copy-status" role="status" aria-live="polite"></p>
        </div>
    </header>
