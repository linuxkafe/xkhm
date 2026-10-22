---
ticket: T044
phase: verify
status: done
created: 2026-10-22
verdict: pass
requires:
  - aes/tickets/T044-plan.md
---

# T044 — Verify: O preçário de domínios e o estado da rede

## Gates

| Gate | Command | Result |
|------|---------|--------|
| PHPUnit | `make test` | **185 tests, 550 assertions** — green |
| PHPStan (nível 5) | `make lint` | **0 erros** |
| Flutter | `make flutter-test` | **49 testes** — green |
| Playwright (API + Flutter) | `make e2e` | **243 testes** — green (2 novos: o preçário e o estado da rede) |
| Tokens | `make tokens-check` | em sincronia |

## Acceptance criteria

| AC | Critério | Evidência | Estado |
|----|----------|-----------|--------|
| AC1 | O preçário mostra os preços **reais** de registar, transferir e renovar | `GET /api/v1/domains/pricing` → `.com {register: 18, transfer: 18, renew: 18, minYears: 1}` | ✅ |
| AC2 | A app mostra o preçário **antes** da pesquisa | `test_flutter_shows_the_domain_price_table` — «Preçário de Domínios», «Registar», «Transferir», «Renovar» | ✅ |
| AC3 | Os preços batem com o WHMCS vivo | comparação feita com Playwright em `https://xk.linuxkafe.com/domainchecker.php`: «.com 1 €18.00, .org 1 €18.00, .pt 1 €35.00» — a API devolve 18 / 18 / 35 | ✅ |
| AC4 | A app tem a página de **Estado da Rede** | `test_flutter_navigates_to_the_network_status` — «Estado actual» e as intervenções comunicadas | ✅ |
| AC5 | Todas as entradas do menu são alcançáveis | `scrollable: true` na `NavigationRail`; `test_flutter_drawer_reaches_every_destination` com 15 destinos | ✅ |
| AC6 | Todos os portões verdes | tabela acima | ✅ |

## A comparação com o WHMCS vivo

Feita com Playwright, no WHMCS de produção (`xk.linuxkafe.com`):

| | WHMCS vivo | xkhm |
|---|---|---|
| Home | Anúncios, Base de Conhecimento, **Estado da Rede**, Afiliados, Contacte-nos, Conta | idem (o «Estado da Rede» é novo) |
| `domainchecker.php` | público; mostra o **Preçário de Domínios** | público; mostra o **preçário** |
| `serverstatus.php` | exige sessão | público (melhor) |
| `networkissues.php` | exige sessão | página nova, pública |

O preçário do WHMCS vivo:

```
TLD    Mínimo de Anos   Registar    Transferir   Renovar
.com              1      €18.00      €18.00      €18.00
.org              1      €18.00      €18.00      €18.00
.pt               1      €35.00      €35.00      €35.00
```

E a API do xkhm, depois da correcção:

```json
{"tld": ".com", "register": 18, "transfer": 18, "renew": 18, "minYears": 1}
{"tld": ".org", "register": 18, "transfer": 18, "renew": 18, "minYears": 1}
{"tld": ".pt",  "register": 35, "transfer": 35, "renew": 35, "minYears": 1}
```

## O defeito do menu

`test_flutter_navigates_to_the_network_status` falhou na primeira
vez: o botão estava «element is outside of the viewport». A causa
não era o ecrã novo — era que a `NavigationRail` **não rola**, e
com 15 destinos os últimos ficavam fora do alcance, no ecrã largo,
onde não há gaveta.

**É o sintoma exacto que o operador descreveu**: «as funcionalidades
como a pesquisa de domínio ou status do servidor também não estão
disponíveis».
