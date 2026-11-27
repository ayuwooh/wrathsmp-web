<?php
$base = '../';
$active = 'vip';
$skipTarget = '#vip-page';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Seja VIP no WrathSMP: benefícios apenas cosméticos, sem pay-to-win, com pagamento seguro pelo MercadoPago.">
    <meta name="theme-color" content="#0E0F0F">
    <meta property="og:type" content="website">
    <meta property="og:locale" content="pt_BR">
    <meta property="og:title" content="WrathSMP - VIP">
    <meta property="og:description" content="Vantagens cosméticas, preço e compra do VIP do WrathSMP.">
    <meta property="og:image" content="../assets/img/background.webp">
    <meta name="twitter:card" content="summary_large_image">
    <title>WrathSMP - VIP</title>
    <link rel="icon" href="../assets/img/wrathsmp_icon.webp">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="../css/vip.css">
    <script src="../js/script.js" defer></script>
    <script src="../js/vip.js" defer></script>
</head>
<body>
<?php require __DIR__ . '/header.php'; ?>

    <main id="vip-page">
        <!-- Apresentação -->
        <section class="vip-hero" aria-labelledby="vip-titulo">
            <img src="../assets/img/background.webp" alt="" class="vip-hero-bg">
            <div class="vip-hero-content">
                <p class="vip-eyebrow">Apoie o servidor</p>
                <h1 id="vip-titulo">VIP <span>WrathSMP</span></h1>
                <p>Vantagens apenas cosméticas, sem pay-to-win, e um jeito direto de ajudar o servidor a continuar crescendo.</p>
                <p><a class="vip-hero-cta" href="#vip-comprar">Ver preço e comprar</a></p>
            </div>
        </section>

        <!-- Informações sobre o VIP vêm antes do preço -->
        <section id="vip-info" class="vip-section" aria-labelledby="vip-info-titulo">
            <h2 id="vip-info-titulo">O que é o VIP?</h2>
            <p>
                Todos os benefícios de VIP são apenas cosméticos, eliminando qualquer elemento
                "pay 2 win". Acreditamos em um ambiente justo e vantagens via dinheiro são contra
                nossa filosofia de game design.
            </p>
            <p>
                Cada pagamento concede <strong data-vip-days>30 dias</strong> de VIP. Se você pagar
                novamente antes de expirar, o tempo restante é somado ao novo período: nada é perdido.
            </p>

            <!-- Benefícios conforme docs/vip_beneficios.md: apenas cosméticos -->
            <ul class="vip-benefits">
                <li>
                    <span class="vip-benefit-icon" aria-hidden="true">💬</span>
                    <h3>Prefixo VIP</h3>
                    <p>Prefixo VIP no chat e na TAB/lista de jogadores.</p>
                </li>
                <li>
                    <span class="vip-benefit-icon" aria-hidden="true">🎨</span>
                    <h3>Sprays</h3>
                    <p>Sprays personalizados para usar no servidor.</p>
                </li>
                <li>
                    <span class="vip-benefit-icon" aria-hidden="true">✨</span>
                    <h3>Trails</h3>
                    <p>Efeitos de trilha para acompanhar você no mundo.</p>
                </li>
            </ul>
        </section>

        <section id="vip-como-funciona" class="vip-section" aria-labelledby="vip-como-titulo">
            <h2 id="vip-como-titulo">Como funciona</h2>
            <ol class="vip-steps">
                <li><strong>Informe seu nick.</strong> Use exatamente o mesmo nome que você usa para entrar no servidor.</li>
                <li><strong>Pague com segurança.</strong> Você é levado ao MercadoPago, que cuida de todo o processamento.</li>
                <li><strong>Receba o VIP automaticamente.</strong> Após a confirmação, o servidor é avisado e seu status é ativado: pode demorar alguns segundos.</li>
                <li><strong>Renove quando quiser.</strong> Pagamentos mensais estendem o prazo. Ao vencer, o status é removido automaticamente até uma nova renovação.</li>
            </ol>
            <p class="vip-note">
                O nick é o identificador do benefício: confira a grafia antes de pagar.
            </p>
        </section>

        <!-- Preço e compra -->
        <section id="vip-comprar" class="vip-section" aria-labelledby="vip-comprar-titulo">
            <h2 id="vip-comprar-titulo">Escolha seu VIP</h2>

            <!-- Mensagem de retorno do MercadoPago (?payment=...) -->
            <div id="vip-payment-notice" class="vip-notice" hidden role="status" aria-live="polite"></div>

            <div class="vip-checkout">
                <div class="vip-price-card">
                    <p class="vip-plan-label">Plano mensal</p>
                    <p class="vip-price" data-vip-price>R$ 8,00</p>
                    <p class="vip-price-period">por <span data-vip-days>30</span> dias</p>
                    <ul class="vip-price-list">
                        <li>Ativação automática após o pagamento</li>
                        <li>Tempo restante somado em cada renovação</li>
                        <li>Remoção automática ao expirar</li>
                    </ul>
                    <p class="vip-price-foot">Pagamento único e seguro via MercadoPago</p>
                </div>

                <div class="vip-form-card">
                    <h3>Finalizar compra</h3>
                    <form id="vip-form" novalidate>
                        <div class="vip-field">
                            <label for="vip-username">Nick do Minecraft</label>
                            <input type="text" id="vip-username" name="username" autocomplete="username"
                                   placeholder="SeuNick" minlength="3" maxlength="16" required>
                            <p class="vip-hint">Somente letras, números e _ (3 a 16 caracteres).</p>
                        </div>
                        <div class="vip-field">
                            <label for="vip-email">E-mail</label>
                            <input type="email" id="vip-email" name="email" autocomplete="email"
                                   placeholder="voce@exemplo.com" required>
                            <p class="vip-hint">Usado para identificá-lo no pagamento.</p>
                        </div>
                        <button type="submit" class="vip-button" id="vip-buy-button">
                            Comprar VIP
                        </button>
                    </form>

                    <p id="vip-form-status" class="vip-form-status" role="status" aria-live="polite"></p>

                    <div class="vip-status-box">
                        <h4>Já é VIP?</h4>
                        <p>Consulte quantos dias restam no seu plano.</p>
                        <button type="button" class="vip-button vip-button-secondary" id="vip-check-button">
                            Consultar status
                        </button>
                        <div id="vip-status-result" class="vip-status-result" role="status" aria-live="polite" hidden></div>
                    </div>
                </div>
            </div>
        </section>

        <section id="vip-faq" class="vip-section" aria-labelledby="vip-faq-titulo">
            <h2 id="vip-faq-titulo">Perguntas frequentes</h2>
            <details>
                <summary>O que é pay 2 win?</summary>
                <p>É quando o dinheiro compra vantagens que mudam o jogo, como mais força, progressão mais rápida ou itens melhores. No WrathSMP isso não acontece: o VIP é apenas cosmético.</p>
            </details>
            <details>
                <summary>O VIP dá vantagens no jogo?</summary>
                <p>Não. Todos os benefícios são apenas cosméticos (prefixo, sprays e trails): não há pay 2 win nem alteração na progressão, no combate ou na economia.</p>
            </details>
            <details>
                <summary>O VIP é ativado na hora?</summary>
                <p>Assim que o MercadoPago confirmar o pagamento. A confirmação pode levar alguns segundos; entre no servidor novamente se o status não aparecer de imediato.</p>
            </details>
            <details>
                <summary>Posso comprar para outro jogador?</summary>
                <p>Informe o nick dele no formulário. Por isso, confira o nome com atenção antes de pagar.</p>
            </details>
            <details>
                <summary>O que acontece quando o VIP acaba?</summary>
                <p>O prazo é consultado em tempo real. Ao vencer, o servidor é avisado para remover as vantagens. Um novo pagamento concede outros <span data-vip-days>30</span> dias.</p>
            </details>
            <details>
                <summary>Preciso criar uma conta no site?</summary>
                <p>Não. Basta o nick e um e-mail para localizarmos seu pagamento e seu jogador.</p>
            </details>
        </section>
    </main>

<?php require __DIR__ . '/footer.php'; ?>

</body>
</html>
