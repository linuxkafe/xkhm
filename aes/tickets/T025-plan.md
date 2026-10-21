---
ticket: T025
phase: plan
status: done
created: 2026-10-21
tier: heavy
verdict: pass
requires:
  - aes/tickets/T023-verify.md
  - aes/tickets/T025.md
produces:
  - aes/tickets/T025-plan.md
blocked_by: ''
---

# T025 — Plan: Migração Completa para Flutter

## Reconnaissance Summary

### Inventário: rota PHP → contrapartida Flutter

| Rota PHP (HTML) | Contrapartida Flutter | Estado |
|------------------|-----------------------|--------|
| `/` (início) | — | **em falta** |
| `/clientarea.php` | Painel | ✅ |
| `/clientarea.php?action=invoices` | Faturas | ✅ |
| `/clientarea.php?action=orders` | — | **em falta** |
| `/domain.php` (lista) | — | **em falta** |
| `/ssl.php` (certificados) | — | **em falta** |
| `/supporttickets.php` | Tickets | ✅ |
| `/affiliates.php` | — | **em falta** |
| `/pwreset.php` (reset) | — | **em falta** |
| `/domainchecker.php` | Procurar Domínio | ✅ |
| `/knowledgebase.php` | Base de Conhecimento | ✅ |
| `/announcements.php` | Anúncios | ✅ |
| `/serverstatus.php` | Estado do Servidor | ✅ |
| `/viewinvoice.php?id=` (detalhe) | — | **em falta** (a API já tem) |
| `/dologin.php`, `/logout.php` | sessão | ✅ |
| `/admin*` | — | **fica em PHP** (fora do âmbito) |
| `/api.php` (API WHMCS legada) | — | **fica como API** |
| `/health`, `/health/db` | — | **infra-estrutura** |

**Sete ecrãs em falta.** A API já tem o detalhe da fatura; falta o resto.

### Inventário da API actual

18 endpoints em `/api/v1`. Faltam: pedidos (`orders`), certificados SSL,
afiliados, e reset de senha.

### Topologia actual

O PHP serve a API **e** as páginas. O Flutter é montado em `/app/`. Para
migrar completamente, `/` passa a entregar a app e as rotas HTML saem.

## Two-Agent Analysis (heavy tier)

### Critic Phase

```
## Como se fosse uma criança
Hoje há duas portas para a mesma casa: a porta do website (HTML) e a porta
da aplicação (Flutter). O senhor quer ficar só com a aplicação. Mas se
tirar a porta do website antes de a aplicação saber fazer tudo o que ela
fazia, o cliente fica à porta fechada.

## Como se fosse um especialista
Seven client-facing HTML routes have no Flutter counterpart. The migration
is not "delete the HTML"; it is "the app must be able to do everything the
pages could do" — otherwise it is a regression, not a migration.

[FAILURE MODE 1] — Deleting the HTML before the app covers it creates an
outage, not a migration. The operator's clients are real (the live WHMCS
serves them today). A route that disappears with no counterpart is a 404
for a paying customer.
Mechanism: `/affiliates.php`, `/ssl.php`, `/pwreset.php` and the orders
list have no Flutter screen. `/viewinvoice.php` has an API endpoint but no
screen.

[FAILURE MODE 2] — `/` is the entry point of the whole product. If it
starts serving the Flutter bundle, the *session* has to work on that
origin — it does, the bundle is already mounted at `/app/` and same-origin
with the API. But the *base href* of the Flutter build is `/app/`; served
at `/` it would request `/main.dart.js` and 404.
Mechanism: the build embeds `--base-href=/app/`. Moving the mount point
requires a rebuild with a different base href, and the two mounts cannot
coexist with different base hrefs.

[FAILURE MODE 3] — "Migrate completely" could be read as "delete the PHP
front-end from the repository". But the PHP is also the **API server** and
the **WHMCS-compatible `/api.php`** that external integrations use.
Deleting the front-end is safe; deleting the PHP is not.
Mechanism: the ticket's scope already excludes moving the backend. The
risk is a misreading of "completamente" that removes the API and takes the
production integrations with it.

[ASSUMPTION] If false — "migrate completely" means also moving the *admin
panel* to Flutter: the consequence is that the ticket needs an admin API
(which does not exist) and the admin screens (a whole product). Mitigation:
the admin is explicitly out of scope in the ticket, and the admin HTML
stays, served under `/admin`. The *client* migration is complete, which is
what the operator's clients use.
Validation: the operator said "migramos também completamente para flutter"
in the context of the client application.

[ALTERNATIVE FRAMING] Instead of "delete the HTML", frame it as "make the
Flutter app the only front-end, and keep the HTML routes alive but
unlinked, for one release". The *product* is migrated; the *code* is
retired later once the traffic data says it is safe. That is a strictly
safer sequencing of the same end state, and it does not need the T025
traffic decision at all — the routes simply stop being advertised.

## Porquê? ×5
1. Why does the app need seven more screens? → Because the pages it
   replaces did that work, and a migration that loses capability is a
   regression.
2. Why is the invoice detail special? → Because the API already exposes it;
   only the screen is missing.
3. Why does `/` need a rebuild? → Because the base href is baked into the
   bundle at build time.
4. Why keep the admin in PHP? → Because there is no admin API, and the
   admin is not the client-facing product.
5. Why not delete the HTML code? → Because the same code path serves the
   404/error pages and the admin; deleting it is a separate, riskier change.

What are we optimizing that we shouldn't be?
We are optimising for "the repository has no HTML left". We should be
optimising for "a client can do everything in the app that they could do
on the website, and nothing on the website is reachable by accident".
```

### Implementor Phase

```
## Como se fosse uma criança
Vamos pôr a aplicação em todas as portas que estavam abertas no website.
Cada porta que falta, abrimos na aplicação. Depois o website deixa de
existir para o cliente — mas o servidor continua a responder à API, que é
onde vivem os dados.

## Como se fosse um especialista
Two deliverables, in this order.

(D1) The seven missing screens, plus the API endpoints they need.

| Screen | New API endpoint | Service reused |
|---|---|---|
| Início | `GET /api/v1/home` (summary + announcements + KB teaser) | composite |
| Pedidos | `GET /api/v1/client/orders` | `OrderRepositoryPDO` |
| Domínios | `GET /api/v1/client/domains` (already exists) | `DomainService` |
| Certificados SSL | `GET /api/v1/client/ssl` | `SslService` + `DomainService` |
| Afiliados | `GET /api/v1/client/affiliate` | `AffiliateService` |
| Reset de senha | `POST /api/v1/auth/forgot-password` | `PasswordResetController` logic |
| Detalhe da fatura | `GET /api/v1/client/invoices/{id}` (already exists) | `BillingService` |

Each screen is a new file under `lib/screens/`, registered as a navigation
destination, and covered by an E2E test that performs the flow.

(D2) The front-end is retired.

- The HTML routes are removed from `public/index.php`.
- `/` serves the Flutter bundle.
- The bundle is rebuilt with `--base-href=/` and mounted at the document root.
- The PHP keeps: `/api/v1`, `/api.php` (WHMCS legacy), `/admin*`,
  `/health`, `/health/db`, and the error pages.
- The 404 page becomes a Flutter screen, so a wrong URL still shows the
  brand.

## ADDRESSING FAILURE MODE 1 (outage during the migration)
Mitigation: **the HTML routes are removed only after every one of them has a
tested Flutter counterpart.** The order is enforced by the build order: the
seven screens land (with their E2E tests) *before* the routes are removed.
The E2E suite is the gate: `test_flutter_app.py` asserts every flow, and a
new `test_routes.py` asserts that the retired HTML routes now answer 404/302
to the app and that the API still answers.

## ADDRESSING FAILURE MODE 2 (base href)
Mitigation: the build is parameterised. `make flutter-build` takes
`FLUTTER_BASE_HREF` (default `/`), and the compose mount is parameterised the
same way. The build is verified by an E2E test that loads `/` and asserts the
app boots — which fails loudly if the base href is wrong.

## ADDRESSING FAILURE MODE 3 (misreading "completely")
Mitigation: the scope is written down and enforced: the PHP **stays** as the
API server. The migration removes the *client-facing HTML*, not the backend.
The ticket's acceptance criteria name the surviving routes explicitly, so a
reviewer can check the boundary.

## ADDRESSING ASSUMPTION (admin panel)
Resolution: the admin stays in PHP under `/admin`, with the admin HTML
untouched. It is not the client-facing product and there is no admin API. The
ticket states this; if the operator wants the admin in Flutter too, that is
a new ticket with an admin API — and this ticket is the prerequisite.

## ADDRESSING ALTERNATIVE FRAMING (keep the HTML for one release)
Accepted and adopted, with one refinement: the HTML routes are **not deleted
from the repository** — they are unregistered from the router. The code stays
(a release can re-register it with one line), but the clients cannot reach
it. This gives the same end state with a much smaller blast radius, and it
keeps the "one release of overlap" option open without a second migration.

## [SOLUTION PROPOSAL]
Stage 1 — the API endpoints for the seven flows.
Stage 2 — the seven Flutter screens + navigation.
Stage 3 — the E2E tests for every flow.
Stage 4 — retire the HTML routes; serve the app at `/`.
Stage 5 — the documentation.

## Porquê? ×5
1. Why API first? → A screen without an endpoint is a mock; the endpoint is
   the durable part.
2. Why tests before retiring the routes? → The tests are the proof that
   the capability was not lost.
3. Why unregister rather than delete? → Same end state, one-line rollback.
4. Why keep `/api.php`? → External integrations use it; it is not a
   front-end.
5. Why parameterise the base href? → The app must work at `/` and the
   bundle is built with the href baked in.

What are we optimizing that we shouldn't be?
We are optimising for "no HTML left in the repository". We should be
optimising for "the client can do everything in the app, and the website
cannot be reached by accident". The unregister approach delivers that end
state without the risk of the delete.
```

## Hostile Analysis (ticket level)

```
ASSUMPTIONS I AM MAKING:
- [KNOWN]    The client-facing HTML is what the operator wants replaced — the
  admin is a separate product surface.
- [INFERRED] The live WHMCS at xk.linuxkafe.com is the real client
  experience and the app must match it in capability.
- [ASSUMED]  The seven missing screens are the complete gap — evidence: the
  route inventory above is exhaustive over `public/index.php`.
- [UNKNOWN]  Whether the operator wants the PHP *code* deleted as well as the
  routes. Impact: low — the code stays, unregistered, so the question can
  be answered later without a migration.

WHAT WAS NOT SPECIFIED:
- The order of the cut-over → resolved: coverage first, retirement second.
- What happens to bookmarks to the old URLs → resolved: 302 to `/`, so no
  bookmark breaks.

ALTERNATIVES NOT CHOSEN:
- Option A: delete the HTML code → rejected: irreversible, and the same
  code path serves the admin and the error pages.
- Option B: leave the HTML reachable and just build the app → rejected: that
  is not a migration; the operator asked for the client to move.

RISKS AND SIDE EFFECTS:
- The Flutter build at `/` changes the mount point → mitigated by
  parameterising the base href and asserting the app boots at `/`.
- The E2E suite's fixtures assume `http://localhost:8083` → the app URL
  becomes the same origin; the fixtures need updating.

COST OF BEING WRONG: high — a client that cannot do what it did is an
outage. Mitigation: the coverage-first order and the E2E gate.

SCOPE BOUNDARY:
In scope: the client-facing front-end, the seven screens, the API
endpoints they need, the retirement of the HTML routes, the tests, the docs.
Out of scope: the admin panel, the backend language, the database.

INVITATION FOR CONTRADICTION:
The most likely flaw: that "completely" also means "the website is gone",
so the operator may expect the PHP front-end code to be deleted. Mitigation:
the routes are unregistered (the website is gone for clients) and the code
stays; the question is answered by the operator, and deleting it is a
one-line change afterwards if they want it.
```

## Technical Approach

**Chosen:** the app covers everything the pages did (seven new screens + the
API endpoints they need), the tests prove it, and *then* the HTML routes are
unregistered and the app is served at the root. The PHP remains the API
server and the admin.

**What will NOT be implemented:** the admin in Flutter; deleting the PHP
front-end code; a second release of overlap (the code stays, unregistered,
so it can be re-registered with one line if the operator wants it).

### Affected files

| File | Operation | Description |
|------|-----------|-------------|
| `src/Http/Controller/Api/ApiV1Controller.php` | modify | New endpoints: home, orders, ssl, affiliate, forgot-password |
| `public/index.php` | modify | Remove the HTML routes; serve the app at `/` |
| `src/Http/View/Layout.php` | keep | Still used by the admin pages and the error pages |
| `flutter/xkhm_app/lib/screens/home_screen.dart` | create | The landing screen |
| `flutter/xkhm_app/lib/screens/orders_screen.dart` | create | The order list |
| `flutter/xkhm_app/lib/screens/domains_screen.dart` | create | The domain list |
| `flutter/xkhm_app/lib/screens/ssl_screen.dart` | create | The certificates |
| `flutter/xkhm_app/lib/screens/affiliate_screen.dart` | create | The affiliate programme |
| `flutter/xkhm_app/lib/screens/password_reset_screen.dart` | create | The password reset |
| `flutter/xkhm_app/lib/screens/invoice_detail_screen.dart` | create | The invoice detail |
| `flutter/xkhm_app/lib/screens/home_shell.dart` | modify | The new destinations |
| `flutter/xkhm_app/lib/services/api_client.dart` | modify | The new calls |
| `flutter/xkhm_app/lib/models/models.dart` | modify | The new DTOs |
| `docker-compose.yml` | modify | The mount moves to the document root |
| `Makefile` | modify | `FLUTTER_BASE_HREF` parameterised |
| `tests/Playwright/test_flutter_app.py` | modify | The new flows |
| `tests/Playwright/test_routes.py` | create | The retired routes answer as designed |
| `docs/FLUTTER.md` | modify | The new topology |

## Testing Strategy

- **API:** each new endpoint is covered by a contract test (status, envelope,
  and the DB row count matching the query).
- **E2E:** each new screen is covered by a flow test — log in, navigate,
  assert the content, and assert it comes from the real table.
- **Retirement:** `test_routes.py` asserts each retired HTML route now
  answers 302 to `/` (or 404), and that the API routes still answer.
- **Regression:** the whole existing suite stays green.

## Verification Criteria

- [ ] `make test`, `make lint`, `make e2e`, `make flutter-test`,
      `make tokens-check` green
- [ ] Every route of the inventory has a tested Flutter counterpart
- [ ] No client-facing HTML route answers HTML
- [ ] The app boots at `/`
- [ ] The documentation reflects the topology

## Estimation

- Complexity: high (> 8h)
- Risk: medium
- Blocking dependencies: no (T023 is done)
