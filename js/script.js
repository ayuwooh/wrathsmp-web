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
