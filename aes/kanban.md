---
project: xkhm
created: 2026-10-20
current_sprint: sprint-01
current_ticket: T044
---

# XKHM — WHMCS Reimplementation

**Goal:** Reverse-engineer the live WHMCS 5.3.x service at `xk.linuxkafe.com` using REA and runtime evidence, then reimplement it as a new, clean service (xkhm) that is fully compatible with the same MySQL database (`linuxkonect`) and the same `configuration.php` settings.

**Origin:** `/home/seyon/dev/whmcs/` — the ionCube stripping/reconstruction project (T001–T048). That project proved the reconstruction path is viable but left 38/735 files reconstructed with some PHP 8.4 lint errors and the rest still encrypted.

## Strategy

xkhm is a **fresh reimplementation**, not a continuation of the reconstruction. It will:
1. Use REA + runtime observation to understand what WHMCS actually does
2. Reimplement only the features present on the live service
3. Connect to the same MySQL database (`linuxkonect`) with the same credentials
4. Be written in a modern language/framework (to be decided in T001)

## Tickets

| ID | Title | Priority | Status |
|----|-------|----------|--------|
| T001 | REA analysis of live service + project scaffold | high | done |
| T002 | Database schema survey + configuration audit | high | done |
| T003 | Feature inventory: client area, billing, domains, tickets, affiliates | high | done |
| T004 | Language/framework selection + architecture decision | high | done |
| T005 | Core domain model implementation | medium | done |
| T006 | Client authentication & session management | medium | done |
| T007 | Billing & invoicing engine | medium | done |
| T008 | Domain management (checker, registrar modules) | medium | done |
| T009 | Support ticketing system | medium | done |
| T010 | Knowledgebase module | low | done |
| T011 | Affiliates module | low | done |
| T012 | Admin panel (core CRUD) | medium | partial — só a autenticação (ver T034) |
| T013 | Payment gateway integrations | medium | done |
| T014 | SSL configuration module | low | done |
| T015 | Server status / network issues | low | done |
| T016 | API layer (WHMCS API compatibility) | medium | done |
| T017 | Docker deployment + production hardening | medium | done |
| T018 | Migration validation: same DB, same data, same config | high | done |
| T019 | Feature parity gap analysis | medium | done |
| T020 | Performance & load testing | low | pending |
| T021 | Playwright E2E test harness (PHP backend) | high | done |
| T022 | Flutter client application (web + mobile) | high | done |
| T023 | Dark mode automático + compatibilidade mobile | high | done |
| T024 | Interruptor manual de tema (system / light / dark) | medium | pending |
| T025 | Migração completa para Flutter (retirar o front-end PHP) | high | done |
| T026 | Importação dos dados de produção + diagnóstico | standard | done |
| T027 | Exposição do MySQL: incidente e correcção | standard | done |
| T028 | Alinhar com o esquema WHMCS 5.3.9 real + formatos de senha | high | done |
| T029 | Rever a exposição dos restantes contentores (ollama, searxng) | medium | pending |
| T030 | Repor a senha do administrador `linuxkafe` na base de produção | medium | pending |
| T031 | Sistema de recuperação de credenciais (cliente + admin) | high | done |
| T032 | Rodar a senha do MySQL + entrega de correio pelo Postfix local | high | done |
| T033 | O e-mail da recuperação leva um link (e a página que faltava) | high | done |
| T034 | O painel de administração (as funções que faltavam) | high | done |
| T035 | Os serviços do cliente, pelo seu tipo | high | done |
| T036 | A interface mobile, modernizada | high | done |
| T037 | MFA para dispositivos novos, sem alterar a base de dados | high | done |
| T038 | O aviso de pagamento: PDF, reenvio, e um rótulo que não mente | high | done |
| T039 | A configuração: a `tblconfiguration` do WHMCS, editável | high | done |
| T040 | O preço e o prazo de renovação do serviço | high | done |
| T041 | A faturação certificada (a integração com a API das finanças) | high | pending |
| T042 | A configuração em secções, com os nomes reais | high | done |
| T043 | A gestão de produtos, serviços e servidores | high | done |
| T044 | O preçário de domínios e o estado da rede | high | done |
| T045 | O cliente pode encomendar um serviço novo | high | done |
| T046 | O cron — e os avisos que não saem para os clientes | high | done |
| T047 | A interface de 2026: o tipo de letra e o logótipo | high | done |
| T048 | O tipo de interligação do servidor | high | done |
| T049 | A interface de 2026, ao estilo do design system | high | done |
| T050 | Associar um serviço a um cliente | high | done |

> **Note on numbering.** T020 was originally reserved for "Performance & load
> testing". The Flutter conversion was planned as **T022** and the Playwright
> harness as **T021** (the harness had to exist first). The tickets were
> renumbered at plan time; T020 keeps its original meaning. The
> front-end replacement decision moved from T023 to **T025** when T023 was
> taken by the dark-mode and mobile work.

## Sprint 01 — status

| Ticket | Title | Status | Verification |
|--------|-------|--------|--------------|
| T021 | Playwright E2E harness | done | 141 E2E tests green; 14 production defects fixed |
| T022 | Flutter client application | done | 15 Flutter E2E + 40 widget tests green; design tokens single-sourced |
| T023 | Dark mode + compatibilidade mobile | done | 70 new assertions; 15 review defects fixed |
| T025 | Migração completa para Flutter | done | 7 ecrãs novos; as 13 rotas HTML respondem 302 para a app |
| T026 | Importação dos dados de produção | done | dump de 372 MB importado; `make db-check`/`db-import` testados |
| T027 | Exposição do MySQL | done | porta no loopback, credenciais locais próprias |
| T028 | Esquema WHMCS 5.3.9 real + formatos de senha | done | 89 PHPUnit + 141 Playwright contra a base **real** |
| T031 | Recuperação de credenciais | done | 103 PHPUnit + 152 Playwright; fluxo completo de cliente e admin, **sem alterar o esquema** |
| T032 | Rotação do MySQL + correio | done | senhas fora do repositório, healthcheck que autentica, tokens entregues por correio |
| T033 | Link de recuperação | done | o e-mail leva um link; o token viaja no fragmento e **não** chega ao servidor |
| T034 | O painel de administração | done | 9 áreas, com pesquisa, filtros, paginação e **escritas**; 128 PHPUnit + 189 Playwright |
| T035 | Os serviços pelo seu tipo | done | o tipo vem do produto, não de uma lista escrita à mão; ecrã novo na app |
| T036 | A interface mobile, modernizada | done | herói, figuras lado a lado, artigos resumidos, pull-to-refresh |
| T037 | MFA para dispositivos novos | done | o segredo em `authdata` (coluna que já existia); a confiança é um cookie assinado |
| T038 | O aviso de pagamento | done | PDF com o `dompdf`, reenvio pela fila com anexo, e a declaração de que não é fiscal |
| T039 | A configuração | done | as 235 definições do WHMCS, agrupadas e editáveis; e o relatório do que falta |
| T040 | Preço e prazo de renovação | done | o prorrateio é cotado antes de aplicado, e registado nas notas do cliente |
| T041 | A faturação certificada | pending | precisa do WFA, do certificado e do NIF do operador (o NIF já está na base) |
| T042 | A configuração em secções | done | 19 secções (as páginas do WHMCS), com os nomes reais do ficheiro de idioma |
| T043 | O catálogo e os servidores | done | produtos com preços por ciclo, servidores com grupos; 4 defeitos apanhados pelos testes |
| T044 | O preçário e o estado da rede | done | 18/18/35 €, como o WHMCS vivo; e a barra do menu passou a rolar |
| T045 | A encomenda, a partir do cliente | done | o catálogo, o pedido, a fatura, e o serviço que nasce quando a fatura é paga |
| T046 | O ciclo de facturação | done | as renovações, as multas, os avisos — e os avisos **não** saem para os clientes |
| T047 | O tipo de letra e o logótipo | done | as fontes servidas de facto, e o logótipo no site, no painel e na app |
| T048 | O tipo do servidor | done | o módulo que aprovisiona: o campo que faltava, e o repositório que não o gravava |
| T049 | A interface de 2026 | done | a escala, o contraste, as bordas em vez das sombras, e o logótipo sobre a placa branca |
| T050 | Associar um serviço | done | o botão «Associar um serviço» na página do cliente, com o preço e a renovação |
| T051 | O login no telefone | done | a wrapper de `Semantics` era a causa; o `textInputAction` e o `keyboardType` adequados |

### Open decisions

- **T024** — the manual theme toggle. The operator asked for *automatic*
  dark mode, so the system preference is the only wired behaviour. The
  palette structure already supports "system / light / dark"; what is
  missing is the storage and the precedence rules.
- **T029** — `ollama` (11434) and `searxng` (8081) are also published on
  `0.0.0.0`. They are not part of xkhm, but they share the same host and
  the same exposure that caused T027. They should be reviewed.
- **T030** — the production password of `linuxkafe` is only recoverable
  by the WHMCS itself. The new recovery flow (T031) gives the *operator*
  a way in; it does not touch the production database.

### ⚠ Outstanding action for the operator

The **production database password was compromised**: it was in
`configuration.php` and in the git history, and it was the password of a
MySQL instance published on the public internet (T027).

Since T032:

- it is **out of every versioned file** — the repository now holds the
  *shape* of the configuration (`${XKHM_DB_PASSWORD:?...}`), never a
  value;
- the **local** database has its own rotated, strong credentials, in an
  untracked `.env` (mode 0600);
- the tool that rotates the production password is written and tested:
  `python3 tools/rotate_db_password.py --host 10.0.3.1 --user linuxkonect --apply`.

What is **left**: the production server (`10.0.3.1`) is not reachable
from this machine, so the rotation itself has to be run where it is.
Rewriting the git history — which would remove the old value from the
commits — is a destructive operation on every clone, and is the
operator's call.

### The rule that T034 brought

> **A ticket without a `TXXX-verify.md` is not `done`.**

The verification is the phase that catches "the acceptance criteria
were not met" — and T012 slipped through without one, and the kanban
carried `done` for a panel that did not exist. The kanban now cites
the verification file for every ticket that has one.

### The rules that T042–T044 brought

> **«Não está disponível» é um sintoma de *layout*, não de
> funcionalidade.** (T044, [[SD-UX-001]])

A `NavigationRail` do Flutter não rola, e com quinze destinos os
últimos ficavam abaixo da dobra — e, no ecrã largo, onde não há
gaveta, **inalcançáveis**. A pergunta a fazer não é «a função
existe?», mas «**o utilizador consegue chegar lá?**». O teste que
distingue as duas coisas é clicar na **última** entrada do menu, no
ecrã mais baixo.

> **Os defaults que não têm significado leem-se do esquema; os que
> têm, escrevem-se à mão.** (T043, [[SD-CI-005]])

O `INSERT` de um produto é recusado (`ERROR 1364`) porque
`tblproducts` tem 70 colunas `NOT NULL` sem default. As colunas em
falta são lidas do `information_schema` e tomam o valor neutro do
seu tipo — e as enumerações e as datas ficam de fora, para que a
ausência seja um erro **falante**.

> **Um ficheiro de dados tem de chegar ao contentor — nos dois
> estágios.** (T042, [[SD-CI-006]])

O `resources/whmcs_admin_labels.php` era copiado só no estágio de
*build*. O contentor respondia 200 e mostrava a configuração
**errada** (175 de 235 definições em «Diversos»). Nenhum aviso — o
código estava certo e o dado faltava. O teste que depende do
ficheiro é o que impede a repetição.

> **Um registo de domínio é uma instalação — e cobra-se a taxa.**
> (T044, [[SD-DOMAIN-013]])

O preço de registar, transferir e renovar está em `tblpricing`, com
`type='domainregister' | 'domaintransfer' | 'domainrenew'`, e na
coluna `msetupfee` — não em `annually`. E o **original é o oráculo**:
os 18/18/35 € vieram de ver o WHMCS vivo com o Playwright, e de
comparar.

### The rules that T045–T047 brought

> **A política e a acção são coisas diferentes.** (T046)

`AutoSuspension` está `on`: a política do WHMCS é suspender os
serviços em atraso. Mas uma instalação **não validada** não pode
agir sobre ela — o dano (o cliente sem serviço) é irreversível. A
definição diz **o que** deve ser feito; o ambiente diz **se** pode
ser feito.

> **Os avisos não saem para os clientes — e a restrição é em dois
> portões.** (T046)

A aplicação não endereça o aviso ao cliente, e o **correio do host**
recusa um aviso cujo destinatário não esteja na lista
(`XKHM_MAILER_NOTICE_RECIPIENTS`), movendo-o para `failed/` com o
motivo. A lista vive no ambiente — a **única fonte** para os dois
runtimes.

> **O `sql_mode` é parte do esquema.** (T046, T045)

O MySQL local era mais restrito que o de produção: o `NO_ZERO_DATE`
recusava a data zero — que é a convenção do WHMCS para «ainda não há
data», e que os **dados importados** têm. O modo local foi
alinhado com o da produção (com o motivo no `docker-compose.yml`).

> **Uma fonte **nomeada** não é uma fonte **servida**.** (T047)

Os tokens diziam `"Inter, system-ui, …"` — com as aspas, é **um**
nome de família, que não existe. O painel estava com a serif e a
app com a Roboto. E o `fontFamily` do Flutter não é uma cadeia: é
uma família. O token passou a ter a família e a cadeia **separadas**.

> **Aprovisionar é usar o que o cliente pagou, não o que está à
> lista.** (T045)

O `tblpricing` é o preço de lista; o `tblhosting.amount` é o que
o cliente paga. São diferentes sempre que há um desconto — e o erro
é silencioso.

### Test gates (all green)

```
make test            # 208 PHPUnit tests — contra a base real
make lint            # PHPStan level 5, 0 errors
make e2e             # 251 Playwright tests (API + Flutter) — dados reais
make flutter-test    # 49 Flutter unit + widget tests
make flutter-e2e     # 15 Playwright tests (Flutter web build)
make tokens-check    # tokens in sync, no hard-coded colours (Dart and CSS)
make db-check        # diagnosis of the production database
make db-test-accounts  # contas de desenvolvimento
make db-reset-tokens   # tokens de recuperação à espera de entrega
make db-reset-client   # repõe a senha de um cliente
make db-reset-admin    # repõe a senha do administrador
make db-env            # cria o .env local com senhas fortes (não versionado)
make db-rotate         # roda a senha da base, verificando o resultado
make db-mail-send      # entrega os tokens de recuperação por correio
make db-mail-send --dry  # mostra o que seria entregue (sem enviar)
make cron             # o ciclo de facturacao (o cron.php do WHMCS)
```
