// Página VIP: preço, checkout do MercadoPago e consulta de dias restantes.
(() => {
    "use strict";

    // vip.html fica em view/: os controllers PHP estão um nível acima.
    const API = "../controller/";
    const form = document.getElementById("vip-form");
    if (!form) return;

    const usernameInput = document.getElementById("vip-username");
    const emailInput = document.getElementById("vip-email");
    const buyButton = document.getElementById("vip-buy-button");
    const formStatus = document.getElementById("vip-form-status");
    const checkButton = document.getElementById("vip-check-button");
    const statusResult = document.getElementById("vip-status-result");
    const paymentNotice = document.getElementById("vip-payment-notice");

    const usernamePattern = /^[a-zA-Z0-9_]{3,16}$/;
    let planDays = 30; // substituído por pricing.php quando disponível

    async function requestJson(url, options) {
        const response = await fetch(url, options);
        let data;
        try {
            data = await response.json();
        } catch (erro) {
            throw new Error("Resposta inválida do servidor.");
        }
        if (!response.ok || data.ok === false) {
            throw new Error(data.error || "Não foi possível concluir a solicitação.");
        }
        return data;
    }

    function setFormStatus(text, kind) {
        formStatus.textContent = text;
        formStatus.className = "vip-form-status" + (kind ? " is-" + kind : "");
    }

    function formatDate(iso) {
        if (!iso) return "—";
        const date = new Date(iso);
        if (Number.isNaN(date.getTime())) return "—";
        return date.toLocaleString("pt-BR", {
            day: "2-digit", month: "2-digit", year: "numeric",
            hour: "2-digit", minute: "2-digit"
        });
    }

    // Preço e duração vêm da configuração do PHP, para não divergirem do checkout.
    requestJson(API + "pricing.php")
        .then((data) => {
            planDays = Number(data.days) || planDays;
            document.querySelectorAll("[data-vip-price]").forEach((el) => {
                el.textContent = data.price_label;
            });
            document.querySelectorAll("[data-vip-days]").forEach((el) => {
                el.textContent = String(data.days);
            });
        })
        .catch(() => {
            // Mantém os valores padrão do HTML se o backend não estiver disponível.
        });

    // Retorno do MercadoPago (back_urls).
    const payment = new URLSearchParams(window.location.search).get("payment");
    const notices = {
        approved: {
            kind: "is-success",
            text: "Pagamento aprovado! O VIP está sendo ativado — pode levar alguns segundos. Use “Consultar status” abaixo para confirmar."
        },
        pending: {
            kind: "",
            text: "Pagamento pendente. Assim que for confirmado, o VIP será ativado automaticamente."
        },
        rejected: {
            kind: "is-error",
            text: "Pagamento não concluído. Você pode tentar novamente quando quiser."
        }
    };
    if (payment && notices[payment] && paymentNotice) {
        paymentNotice.textContent = notices[payment].text;
        paymentNotice.className = "vip-notice " + notices[payment].kind;
        paymentNotice.hidden = false;
    }

    // Compra
    form.addEventListener("submit", async (event) => {
        event.preventDefault();
        setFormStatus("");

        const username = usernameInput.value.trim();
        const email = emailInput.value.trim();

        if (!usernamePattern.test(username)) {
            setFormStatus("Informe um nick válido (3 a 16 letras, números ou _).", "error");
            usernameInput.focus();
            return;
        }
        if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
            setFormStatus("Informe um e-mail válido.", "error");
            emailInput.focus();
            return;
        }

        buyButton.disabled = true;
        buyButton.textContent = "Abrindo pagamento...";
        try {
            const data = await requestJson(API + "checkout.php", {
                method: "POST",
                headers: { "Content-Type": "application/json" },
                body: JSON.stringify({ username, email })
            });
            setFormStatus("Pagamento criado! Redirecionando para o MercadoPago...", "success");
            window.location.assign(data.checkout_url);
        } catch (erro) {
            setFormStatus(erro.message, "error");
            buyButton.disabled = false;
            buyButton.textContent = "Comprar VIP";
        }
    });

    // Consulta de status
    async function checkStatus() {
        const username = usernameInput.value.trim();
        if (!usernamePattern.test(username)) {
            statusResult.hidden = false;
            statusResult.className = "vip-status-result";
            statusResult.textContent = "Digite um nick válido acima para consultar.";
            return;
        }

        checkButton.disabled = true;
        checkButton.textContent = "Consultando...";
        try {
            const data = await requestJson(API + "status.php?username=" + encodeURIComponent(username));
            statusResult.hidden = false;

            if (!data.found) {
                statusResult.className = "vip-status-result";
                statusResult.innerHTML = "Nenhum registro encontrado para <strong>" +
                    escapeHtml(data.username) + "</strong>.";
                return;
            }

            if (data.vip) {
                const percent = Math.min(100, Math.max(0, (data.days_remaining / planDays) * 100));
                statusResult.className = "vip-status-result is-active";
                statusResult.innerHTML =
                    "<strong>VIP ativo</strong>" +
                    "<span class='vip-status-days'>" + data.days_remaining + " dia(s) restante(s)</span>" +
                    "<div>Expira em: " + formatDate(data.expires_at) + "</div>" +
                    "<div class='vip-status-bar' aria-hidden='true'><span style='width:" + percent + "%'></span></div>";
            } else {
                statusResult.className = "vip-status-result";
                statusResult.innerHTML = "<strong>" + escapeHtml(data.username) +
                    "</strong> não possui VIP ativo no momento.";
            }
        } catch (erro) {
            statusResult.hidden = false;
            statusResult.className = "vip-status-result";
            statusResult.textContent = erro.message;
        } finally {
            checkButton.disabled = false;
            checkButton.textContent = "Consultar status";
        }
    }

    function escapeHtml(text) {
        return String(text).replace(/[&<>"']/g, (char) => ({
            "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;"
        }[char]));
    }

    if (checkButton) {
        checkButton.addEventListener("click", checkStatus);
    }
})();
