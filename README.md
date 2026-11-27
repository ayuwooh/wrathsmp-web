# Sistema VIP: WrathSMP

Visão geral do fluxo:

1. O jogador informa nick + e-mail em `view/vip.php` e clica em **Comprar VIP**.
2. `controller/checkout.php` cria a preferência no MercadoPago e devolve a URL de checkout.
3. O MercadoPago processa o pagamento e chama `controller/webhook.php`.
4. O webhook busca o pagamento na API, grava o registro e **soma os dias ao `vip_until` do jogador**.
5. Um aviso é enviado ao plugin do servidor (`SET_VIP`) para ativar o status in-game.
6. Um cron remove (`REMOVE_VIP`) quando a data expira. Renovações mensais somam mais dias à data atual.

## 1. Banco de dados

Crie o banco e importe `database/schema.sql`:

```bash
mysql -u root -p -e "CREATE DATABASE wrathsmp CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql -u root -p wrathsmp < database/schema.sql
```

## 2. Variáveis de ambiente

Todas as configurações são lidas de `getenv()` em `model/config.php` (nunca commite credenciais).
No Apache você pode usar `SetEnv` no `.htaccess`; no Nginx/PHP-FPM use `env[...]` no pool;
em desenvolvimento, `export` antes de subir o servidor.

| Variável | Exemplo | Descrição |
| --- | --- | --- |
| `DB_HOST` | `localhost` | Host do MySQL |
| `DB_PORT` | `3306` | Porta do MySQL |
| `DB_NAME` | `wrathsmp` | Nome do banco |
| `DB_USER` | `wrathsmp_user` | Usuário do banco |
| `DB_PASS` | `senha` | Senha do banco |
| `SITE_URL` | `https://wrathsmp.com.br` | URL pública, sem barra final |
| `MP_ACCESS_TOKEN` | `APP_USR-...` | Access token do MercadoPago |
| `MP_WEBHOOK_SECRET` | `...` | Secret gerado no painel do MP (assinatura `x-signature`) |
| `MP_SANDBOX` | `true` | `true` = link de teste; `false` = produção |
| `VIP_PRICE` | `8.00` | Preço mensal |
| `VIP_DAYS` | `30` | Dias concedidos por pagamento |
| `VIP_CURRENCY` | `BRL` | Moeda |
| `VIP_TITLE` | `VIP WrathSMP - 30 dias` | Nome do item no checkout |
| `VIP_PLUGIN_URL` | `http://127.0.0.1:8080/vip` | Endpoint HTTP do plugin |
| `VIP_PLUGIN_TOKEN` | `token-longo-e-aleatorio` | Segredo compartilhado com o plugin |
| `VIP_PLUGIN_TIMEOUT` | `5` | Timeout em segundos |
| `VIP_CRON_TOKEN` | `token-aleatorio` | Protege `expire.php` quando chamado por HTTP |

Requisitos: PHP 8.1+ com `pdo_mysql`, `curl`, `mbstring` e MySQL 5.7+.
Também existe `env.example` com linhas `export` prontas para copiar e preencher.

## 3. MercadoPago

- **URL de notificação**: `https://seusite/controller/webhook.php` (precisa ser pública e HTTPS).
- **Webhook secret**: gere nas configurações da aplicação e coloque em `MP_WEBHOOK_SECRET`.
  Sem ele a assinatura não é validada (use apenas em desenvolvimento local).
- O `external_reference` da preferência é o `id` do usuário no banco; é assim que o
  webhook sabe quem receberá o VIP.
- `binary_mode = true`: pagamentos aprovados ou recusados direto (sem "pendente"),
  o que evita creditar VIP antes da confirmação.
- URLs de retorno: `view/vip.php?payment=approved|pending|rejected`. Elas **não** dão o VIP;
  apenas avisam o usuário. O crédito real acontece no webhook.
- O webhook responde `500` em caso de falha para que o MercadoPago reenvie a notificação.

## 4. Contrato do plugin (lado do servidor)

O PHP envia um `POST` JSON para `VIP_PLUGIN_URL` sempre que o status muda:

```http
POST /vip HTTP/1.1
Authorization: Bearer <VIP_PLUGIN_TOKEN>
X-WrathSMP-Signature: <hmac_sha256 do corpo JSON usando VIP_PLUGIN_TOKEN>
Content-Type: application/json

{
  "action": "SET_VIP",
  "username": "steve",
  "uuid": "00000000-0000-0000-0000-000000000000",
  "vip": true,
  "expires_at": "2026-12-19T12:00:00+00:00",
  "reason": "Pagamento MercadoPago #123456"
}
```

Para remover, `action` é `REMOVE_VIP`, `vip` é `false` e `expires_at` é `null`.

Resposta esperada: `HTTP 200` com `{"ok": true}`.
O plugin deve validar o header `Authorization` e, de preferência, conferir a assinatura
HMAC do corpo cru com o mesmo token. Depois disso, basta aplicar permissões/grupos
(como `lp user <nick> permission set ...` do LuckPerms, ou o que o servidor usar).

> O plugin deve aceitar o mesmo nick repetido sem duplicar benefícios: as chamadas
> são idempotentes por design (reenvios do webhook e do cron são normais).

## 5. Expiração automática

Adicione ao crontab (a cada 5 minutos, ajustável):

```cron
*/5 * * * * php /caminho/do/projeto/controller/expire.php >> /caminho/do/projeto/cron.log 2>&1
```

Ou, sem acesso ao cron, agende uma requisição HTTP autenticada:

```bash
curl -H "X-Cron-Token: $VIP_CRON_TOKEN" https://seusite/controller/expire.php
```

Além do cron, `status.php` também remove VIP vencido como rede de segurança quando
alguém consulta o status.

## 6. Estrutura

```text
.
├── conexao.php          # conexão PDO com o banco
├── index.php            # front-end (página inicial e rotas para view/)
├── view/header.php      # cabeçalho compartilhado (incluído pelas páginas)
├── view/footer.php      # rodapé compartilhado (incluído pelas páginas)
├── controller/          # endpoints HTTP chamados pelo front-end e pelo MercadoPago
├── database/            # schema SQL
├── model/               # configuração, bootstrap e classes de domínio
├── view/                # páginas PHP (wiki, changelog, VIP, Mapa e suporte)
├── css/ e js/           # assets estáticos do site
└── assets/              # imagens
```

| Arquivo | Função |
| --- | --- |
| `view/header.php` | Cabeçalho e menu compartilhados; use `$base`, `$active` e `$skipTarget` antes do include |
| `view/footer.php` | Rodapé compartilhado |
| `model/config.php` | Configuração via variáveis de ambiente |
| `model/bootstrap.php` | Carrega classes, config e tratamento de erros |
| `conexao.php` | Conexão PDO |
| `model/Response.php` | Respostas JSON padronizadas |
| `model/User.php` | Entidade do jogador: dias restantes, concessão e remoção |
| `model/MercadoPagoClient.php` | Preferência, consulta de pagamento e assinatura do webhook |
| `model/PluginClient.php` | Comunicação HTTP com o plugin do Minecraft |
| `model/VipService.php` | Regras de negócio, idempotência e efeitos externos |
| `controller/checkout.php` | Inicia a compra |
| `controller/webhook.php` | Recebe a confirmação do pagamento |
| `controller/status.php` | Consulta dias restantes |
| `controller/pricing.php` | Preço/duração para o front-end |
| `controller/expire.php` | Cron de expiração e reenvios |
| `database/schema.sql` | Tabelas `users`, `payments` e `vip_events` |

## 7. Ajustes de conteúdo

Os textos de benefícios ficam em `view/vip.php` e devem acompanhar
`docs/vip_beneficios.md` (somente vantagens cosméticas). Ajuste a lista de vantagens e o preço
configurando `VIP_PRICE`/`VIP_DAYS` (o valor exibido na página vem de `pricing.php`).

## 8. Segurança e extensões

- Use **HTTPS** em produção (checkout, webhook e chamada ao plugin).
- Nunca exponha `MP_ACCESS_TOKEN`, `MP_WEBHOOK_SECRET` ou `VIP_PLUGIN_TOKEN` no front-end.
- Se o site ficar aberto ao público, adicione limitação de requisições (rate limit)
  e/ou reCAPTCHA em `checkout.php` para evitar criação de preferências em massa.
- Reembolsos (`refunded`/`charged_back`) podem ser tratados no `processPayment()`:
  a política atual credita apenas pagamentos aprovados e não revoga por reembolso.
- Para assinaturas recorrentes de verdade, troque a preferência por um plano de
  assinatura (preapproval) do MercadoPago; a tabela `payments` já serve de histórico.
