// Copia um texto para a área de transferência.
// O navigator.clipboard exige HTTPS ou localhost; o bloco abaixo serve como reserva.
async function copiarTexto(texto) {
    if (navigator.clipboard && window.isSecureContext) {
        await navigator.clipboard.writeText(texto);
        return;
    }

    const campo = document.createElement("textarea");
    campo.value = texto;
    campo.style.position = "fixed";
    campo.style.opacity = "0";
    document.body.appendChild(campo);
    campo.select();
    document.execCommand("copy");
    campo.remove();
}

const botoes = document.querySelectorAll(".ip-copy");
const aviso = document.getElementById("copy-status");

botoes.forEach((botao) => {
    botao.addEventListener("click", async () => {
        // O texto vem do <code>; se mudar o IP no HTML, não precisa mudar aqui.
        const ip = botao.querySelector("code").textContent.trim();

        try {
            await copiarTexto(ip);
            aviso.textContent = "IP copiado!";
        } catch (erro) {
            aviso.textContent = "Não foi possível copiar. Selecione o IP manualmente.";
        }

        // Limpa o aviso depois de 2 segundos.
        setTimeout(() => {
            aviso.textContent = "";
        }, 2000);
    });
});

// Botão "#" ao lado dos títulos: copia o link direto para a seção.
document.querySelectorAll("#wiki h2[id], #wiki h3[id], #changelog h3[id]").forEach((titulo) => {
    const botao = document.createElement("button");
    botao.type = "button";
    botao.className = "anchor-copy";
    botao.textContent = "#";
    botao.dataset.tooltip = "Copiar link";
    botao.setAttribute("aria-label", `Copiar link para ${titulo.textContent.trim()}`);

    botao.addEventListener("click", async () => {
        const url = location.href.split("#")[0] + "#" + titulo.id;
        try {
            await copiarTexto(url);
            aviso.textContent = "Link da seção copiado!";
        } catch (erro) {
            aviso.textContent = "Não foi possível copiar o link.";
        }
        setTimeout(() => {
            aviso.textContent = "";
        }, 2000);
    });

    titulo.appendChild(botao);
});

// Índice: marca o link da seção que está sendo lida.
const linksIndice = Array.from(document.querySelectorAll('.wiki-sidebar a[href^="#"]'))
    .map((link) => ({ link, alvo: document.getElementById(link.getAttribute("href").slice(1)) }))
    .filter((item) => item.alvo);

function destacarSecao() {
    let atual = null;
    linksIndice.forEach(({ link, alvo }) => {
        if (alvo.getBoundingClientRect().top <= 140) {
            atual = link;
        }
        link.removeAttribute("aria-current");
    });
    if (atual) {
        atual.setAttribute("aria-current", "location");
    }
}

// Barra de progresso de leitura do artigo.
const barraProgresso = document.querySelector(".reading-progress");
const artigo = document.getElementById("wiki");

function atualizarProgresso() {
    if (!barraProgresso || !artigo) return;
    const inicio = artigo.offsetTop;
    const fim = artigo.offsetHeight - window.innerHeight + 200;
    const valor = (window.scrollY - inicio + 120) / Math.max(fim, 1);
    const percentual = Math.min(100, Math.max(0, valor * 100));
    barraProgresso.style.setProperty("--progresso", `${percentual}%`);
}

let atualizando = false;
window.addEventListener("scroll", () => {
    if (atualizando) return;
    atualizando = true;
    requestAnimationFrame(() => {
        destacarSecao();
        atualizarProgresso();
        atualizando = false;
    });
}, { passive: true });

destacarSecao();
atualizarProgresso();

// No celular, o índice começa fechado e pode ser aberto pelo botão.
const sidebar = document.querySelector(".wiki-sidebar");
const botaoIndice = document.querySelector(".sidebar-toggle");
if (sidebar && botaoIndice) {
    const telaEstreita = window.matchMedia("(max-width: 900px)");

    function definirIndice(fechado) {
        sidebar.classList.toggle("is-collapsed", fechado);
        botaoIndice.setAttribute("aria-expanded", String(!fechado));
    }

    definirIndice(telaEstreita.matches);
    botaoIndice.addEventListener("click", () => {
        definirIndice(!sidebar.classList.contains("is-collapsed"));
    });
    telaEstreita.addEventListener("change", (evento) => definirIndice(evento.matches));
}
