---
ticket: T020
phase: plan
status: done
created: 2026-10-21
tier: heavy
verdict: pass
requires:
  - aes/tickets/T019.md
  - aes/tickets/T020.md
  - aes/tickets/T021-plan.md
produces:
  - aes/tickets/T020-plan.md
blocked_by: ''
---

# T020 — Plan: Flutter Client Application (Web + Mobile)

## Reconnaissance Summary

| Finding | Detail |
|---------|--------|
| Flutter SDK | **Not installed at recon time** → now installed at `/opt/flutter`, Flutter 3.47.7 stable, Dart 3.13.5 |
| Dart | Bundled with the SDK (`/opt/flutter/bin/dart`) |
| Playwright | Python 1.63.0 present (T021 harness) — usable to drive `flutter run -d web-server` |
| Existing front-end | Server-rendered PHP/Slim 4 pages; thin inline HTML; CSS files exist under `public/templates/default/css/` but are **not linked** from the markup |
| Backend contract | 19 unique routes; JSON already served by `/health`, `/health/db`, `/api.php`; HTML by the rest |
| DB | Single source of truth `linuxkonect` (WHMCS schema) — **no schema change permitted** |
| Design source | `http://www.linuxkafe.com/wp-content/themes/linuxkafe/style.css` — 8 CSS custom properties, 2 font families, 4 layout tokens |
| Machine | 6 cores, 15 GB RAM, 234 GB free disk — sufficient for a web build |

### Recon-derived constraint
The existing PHP pages return **HTML**, not JSON, for most content screens. A Flutter
client cannot consume HTML. Therefore T020 **must** add a JSON API surface to the PHP
backend. This is a real architectural dependency that the original ticket did not state
explicitly — it is now stated here and scoped in.

---

## Two-Agent Analysis (heavy tier)

### Critic Phase

```
## Como se fosse uma criança
Imagine que o site é um livro. Hoje o livro é escrito em português, e nós
queremos mostrá-lo num ecrã grande de tv. Mas a televisão só sabe mostrar
números (JSON), não frases bonitas (HTML). Então temos de traduzir o livro
para números primeiro. E depois temos de pôr as cores certas: amarelo
#f8b400, fundo escuro #2d2d2d.

## Como se fosse um especialista
The Flutter client is a separate presentation tier. It requires a machine-readable
API over the same `linuxkonect` schema. The PHP backend today emits HTML for
14 of 19 routes. Only /health, /health/db, /api.php emit JSON. Therefore T020 is
really two coupled pieces: (a) a JSON API layer over the existing repositories,
(b) a Flutter client consuming it. Coupling them in one ticket is what makes it heavy.

[FAILURE MODE 1] — Scope coupling: building the API and the client in one ticket means a
failure in either blocks the other, and the operator sees nothing shippable.
Mechanism: The Flutter client cannot be verified end-to-end without the API; the API
cannot be verified as "useful" without the client. If the API contract drifts from what
the client expects, both fail together at the last minute.

[FAILURE MODE 2] — Database compatibility regression: the WHMCS schema is fixed and
foreign; a Flutter-driven rewrite of query logic risks diverging from the WHMCS
semantics that the live service depends on (e.g. `tblclients.status` enum values,
`tblorders.status` enum, WHMCS 5.3.x MD5/salted-MD5 password formats).
Mechanism: Reimplementing repositories in Dart would duplicate auth semantics and risk
breaking the "fully compatible with the current database" requirement the operator
stated explicitly.

[FAILURE MODE 3] — Design-token drift: the operator asked for "the CSS we already have
at linuxkafe.com" and "the same colour scheme". A Flutter app that hard-codes #f8b400 in
widgets, and separately a CSS file that hard-codes it, produces two sources of truth
that silently diverge the first time someone edits one.
Mechanism: Flutter has no CSS custom-property support; the port is manual. Without a
generated/single-source token file, the two representations drift.

[ASSUMPTION] If false — "the operator wants the Flutter app to be a replacement for the
PHP web front-end" (i.e. the PHP pages should be retired): the consequence is that we
have built a Flutter client that nobody routes to, and a PHP site that still serves
stale HTML. We would have duplicated the front-end instead of replacing it.
Validation: The ticket text says "convert this application to flutter". A true
replacement means the PHP front-end becomes an API-only service.

[ALTERNATIVE FRAMING] Instead of "build a Flutter client + add a JSON API", frame it as
"make the backend API-first, then the Flutter app is just the first consumer". The
backend becomes the durable asset; the Flutter app is a swappable presentation tier.
This also lets the Playwright suite from T021 test the API contract directly, and lets
the Flutter E2E suite reuse the same contract.

## Porquê? ×5
1. Why does the Flutter app need JSON? → Because Flutter's http package parses JSON,
   and rendering raw HTML would require a WebView, which defeats the purpose.
2. Why does the JSON need to live in PHP rather than Dart? → Because the DB credentials,
   WHMCS password hashing, and query semantics must not be duplicated; one implementation
   of auth means one place to be compatible with the live schema.
3. Why keep the PHP pages at all? → Because "fully compatible with the current database"
   plus a live WHMCS-derived client base means the HTML pages still serve real traffic
   during migration; retiring them is a separate, later decision.
4. Why must the design tokens be single-source? → Because the operator's acceptance
   criterion is "same colour scheme" across the CSS we already have AND the Flutter app.
5. Why single-source? → Because two hand-maintained token lists provably diverge.

What are we optimizing that we shouldn't be?
We are optimising for "the Flutter app looks like linuxkafe.com". We should be optimising
for "the operator can maintain one definition of the brand and both clients obey it".
The latter is cheaper and verifiable; the former is a one-time screenshot comparison.
```

### Implementor Phase

```
## Como se fosse uma criança
Vamos fazer três coisas. Primeiro, um livro de cores só — um ficheiro que diz
"a cor principal é #f8b400" — e tanto o CSS como o Flutter vão ler desse livro,
assim nunca ficam diferentes. Segundo, damos ao servidor a capacidade de
responder com números em vez de frases, quando alguém pede. Terceiro, fazemos a
app de telemóvel que lê esses números e desenha as coisas com as cores do livro.

## Como se fosse um especialista
Three deliverables, ordered so each is independently verifiable:

(D1) Single-source design tokens.
  - `assets/design_tokens.json` — canonical machine-readable token set, derived from
    linuxkafe.com's `:root` block, with a `source_url` + `source_sha256` field.
  - `tools/sync_tokens.py` — generates:
      * `public/templates/default/css/tokens.css` (`:root { --color-primary: ... }`)
      * `flutter/xkhm_app/lib/theme/tokens.dart` (Dart constants)
  - The generator is the ONLY place that hard-codes a hex. Both outputs are generated
    artefacts. A CI check (`make tokens-check`) regenerates and diffs; drift = failure.
  - Flutter widgets reference `LkColors.primary`, never a literal.

(D2) JSON API layer in the existing PHP backend, reusing the existing
     repositories and services verbatim (no query reimplementation).
  - New `src/Http/Controller/Api/` controllers returning `application/json`.
  - New routes under `/api/v1/*`, deliberately namespaced so they never shadow
    the WHMCS-compatible `/api.php`.
  - Endpoints (one per Flutter screen need):
      GET /api/v1/health
      GET /api/v1/auth/login      (POST, client credentials -> session token)
      GET /api/v1/auth/session
      POST /api/v1/auth/logout
      GET /api/v1/client/summary      (dashboard: services, invoices, tickets counts)
      GET /api/v1/client/invoices
      GET /api/v1/client/invoices/{id}
      GET /api/v1/client/domains
      GET /api/v1/client/tickets
      GET /api/v1/client/tickets/{id}
      POST /api/v1/client/tickets
      GET /api/v1/knowledgebase
      GET /api/v1/knowledgebase/categories
      GET /api/v1/knowledgebase/{id}
      GET /api/v1/announcements
      GET /api/v1/domains/pricing
      GET /api/v1/serverstatus
  - Auth: reuse `Xkhm\Service\AuthService` unchanged. This is the key to
    FAILURE MODE 2 — the WHMCS MD5 / salted-MD5 semantics live in exactly one place.
  - Session: reuse the PHP session cookie (`PHPSESSID`) so the Flutter web build and
    the PHP pages can share a login. No new token system, no new secret.

(D3) Flutter client.
  - `flutter/xkhm_app/` — `flutter create --platforms=web,android,ios --org pt.linuxkafe`
  - State: `provider` (small, no codegen, no build_runner dependency) — chosen over
    Riverpod/Bloc to keep the dependency surface auditable and the build fast.
  - `ApiClient` — plain `http` package, base URL from `--dart-define=API_BASE_URL`,
    cookie-aware via `http` + `Cookie` header persisted in `shared_preferences`.
  - Screens per the ticket ACs, all pt-BR, all token-driven.
  - `LkTheme` (Material 3) seeded from the generated `tokens.dart`.

## ADDRESSING FAILURE MODE 1 (scope coupling)
Mitigation: **Staged delivery inside the ticket, with a green gate after each stage.**
  Stage A (tokens) → gate: `make tokens-check` green + PHP pages render with new tokens.
  Stage B (API)    → gate: curl/pytest contract tests green for every endpoint.
  Stage C (Flutter)→ gate: `flutter build web` succeeds + Playwright suite green.
The ticket is not "done" until Stage C, but each stage is independently shippable and
independently revertable, so a failure in C does not destroy A or B.

## ADDRESSING FAILURE MODE 2 (DB compatibility regression)
Mitigation: The Dart side contains **zero SQL and zero password-hashing logic**. All data
access stays in the existing `Xkhm\Repository\*` PDO classes. Auth hashing stays in
`Xkhm\Service\AuthService`. The only new PHP is a serialisation layer over existing
services. Regression gate: the existing PHPUnit suite must stay green, and the new API
contract tests query the same DB rows the HTML pages render — asserted by comparing
counts, not just status codes.

## ADDRESSING FAILURE MODE 3 (design-token drift)
Mitigation: `tools/sync_tokens.py` is the single writer. `make tokens-check` regenerates
both outputs into a temp dir and diffs against the committed files; any drift fails the
build. The Flutter side imports generated `tokens.dart`; a grep-based guard in the
E2E/lint step fails if any `.dart` file contains a raw `#RRGGBB` literal.

## ADDRESSING ASSUMPTION ("replacement vs. additional client")
Resolution: This ticket delivers the Flutter app **as the new primary client UI** and
demotes the PHP HTML pages to a **compatibility fallback** that remains fully functional.
Rationale: (a) the operator asked to "convert", so the Flutter app must be complete and
first-class; (b) retiring the PHP pages while live WHMCS-derived clients still use them
is a business risk that is not this ticket's call. The PHP pages are therefore left
working, and the routing decision is deferred to a follow-up ticket (T022) that can be
made with production traffic data.

## ADDRESSING ALTERNATIVE FRAMING ("make the backend API-first")
Accepted and adopted: the backend gains a first-class, versioned JSON API and the Flutter
app is explicitly "the first consumer". `/api/v1` versioning is chosen so the
WHMCS-compatible `/api.php` (which external integrations depend on) is untouched.

## [SOLUTION PROPOSAL]
Deliver in three gated stages as above:
  A. `assets/design_tokens.json` + `tools/sync_tokens.py` + generated CSS/Dart + `make tokens-check`
  B. `/api/v1/*` JSON controllers over existing repositories/services + contract tests
  C. Flutter app (web/android/ios) consuming `/api/v1`, token-themed, pt-BR
  D. Playwright E2E against the Flutter web build (reuses the T021 harness contract)

## Porquê? ×5
1. Why tokens first? → Because both clients consume them; building the API first would
   mean retrofitting a colour palette onto screens that were already hard-coded.
2. Why tokens as JSON + generator? → Because a generator makes drift detectable
   (CI diff), while two hand-written files make drift merely likely.
3. Why API before Flutter? → Because the API is testable without a browser; it is the
   cheaper half to get right, and it is the durable asset.
4. Why reuse the PHP session rather than invent tokens? → Because the operator's
   requirement is DB compatibility, and one auth implementation means one set of
   WHMCS-hashing semantics to maintain.
5. Why is the PHP front-end not deleted? → Because deletion is irreversible and the
   traffic decision belongs to the operator with production data in hand.

What are we optimizing that we shouldn't be?
We are optimising for "Flutter is the deliverable". We should be optimising for
"the operator has one maintainable codebase with two clients and one source of truth for
brand and auth". The latter is what the staged, generated, shared-source design delivers.
```

---

## Hostile Analysis (ticket-level)

```
ASSUMPTIONS I AM MAKING:
- [KNOWN]    Flutter 3.47.7 is installable and buildable here — verified: SDK installed
  at /opt/flutter, `flutter --version` reports Dart 3.13.5, 234 GB disk free.
- [KNOWN]    The operator's "use the CSS we already have at linuxkafe.com" means the
  colour scheme and typography, not the literal CSS file — Flutter cannot consume CSS.
- [INFERRED] The operator wants the Flutter app to be the primary client UI — from
  "convert this application to flutter". Mitigated by keeping the PHP pages working.
- [ASSUMED]  The operator can run `flutter` — impact if false: the E2E for Flutter
  cannot run on their machine; mitigated by documenting the exact commands and by
  shipping a prebuilt web bundle under `flutter/xkhm_app/build/web` for the Playwright
  suite to serve statically.
- [UNKNOWN]  Whether the operator's live deployment target for Flutter is web or native
  mobile. Both are produced; the ticket does not commit to a store release.

WHAT WAS NOT SPECIFIED (that matters):
- Which screens exactly → resolved: enumerated in the ticket ACs and the API endpoint
  list above.
- Whether the Flutter app should replace or supplement the PHP pages → resolved above:
  supplement for now, replace decision deferred with a named follow-up ticket.

ALTERNATIVES NOT CHOSEN:
- Option A: WebView wrapper around the existing PHP pages — rejected: no new UI, no
  responsive rewrite, and it violates the operator's intent to use the Flutter design.
- Option B: Reimplement the data layer in Dart with a DB driver — rejected: duplicates
  WHMCS auth/column semantics; violates the DB-compatibility requirement.
- Option C: Flutter + BLoC/Riverpod + freezed + json_serializable — rejected: adds
  build_runner codegen, slowing the loop, for no functional gain at this scale.

RISKS AND SIDE EFFECTS:
- `flutter build web` downloads engine artifacts (~1 GB) → mitigated by pre-warming
  during the ticket.
- Flutter web renders to canvas → Playwright DOM assertions on text will fail unless
  the semantics tree is enabled. Mitigation: Flutter web exposes an accessibility
  DOM when `--web-renderer html` semantics is on; the E2E suite must use
  `flt-semantics` selectors or the `SemanticsBinding` tree. This is a REAL constraint
  and it shapes the E2E strategy in Stage C.
- The new `/api/v1` surface enlarges the attack surface → mitigation: it reuses the
  existing session auth and the existing CSRF posture; no new auth mechanism.
- Generated token files can be hand-edited → mitigation: `make tokens-check` diff gate.

COST OF BEING WRONG: high → confirmed with the operator before Stage C build-out.
Stages A and B are cheap and safe to proceed with immediately.

SCOPE BOUNDARY:
In scope: tokens + sync, `/api/v1` JSON layer, Flutter client (web/android/ios),
Playwright E2E for the Flutter build, pt-BR, token-driven styling.
Out of scope: retiring the PHP pages, admin UI in Flutter, payment capture, store
release, i18n framework beyond pt-BR strings.

INVITATION FOR CONTRADICTION:
The most likely flaw: the operator may not have a working Flutter toolchain in their
environment, in which case the "conversion" is unusable to them regardless of how
correct it is. Mitigation: ship the built web bundle and a one-command serve script;
also document `flutter` SDK bootstrap in the README. Second-most-likely: the operator
wanted a *visual* clone of linuxkafe.com landing page, not a client-area app. Mitigation:
Stage A includes a `HomeScreen` reproducing the linuxkafe.com header/hero/cards, so the
visual intent is satisfied early and visibly.
```

## Technical Approach

**Chosen:** three gated stages over the existing PHP backend, with a single-source
design-token pipeline, a versioned JSON API, and a Flutter client on top.

**What will NOT be implemented:** Dart-side SQL, a new auth/token system, retirement of
the PHP HTML pages, admin UI in Flutter, payment capture, non-pt-BR locales.

### Affected files

| File | Operation | Description |
|------|-----------|-------------|
| `assets/design_tokens.json` | create | Canonical token set + source URL + source hash |
| `tools/sync_tokens.py` | create | Generator: tokens.json → CSS + Dart |
| `public/templates/default/css/tokens.css` | create (generated) | `:root` custom properties |
| `public/templates/default/css/whmcs.css` | modify | Consume `tokens.css` instead of hard-coding |
| `flutter/xkhm_app/lib/theme/tokens.dart` | create (generated) | Dart constants |
| `flutter/xkhm_app/lib/theme/lk_theme.dart` | create | Material 3 theme from tokens |
| `flutter/xkhm_app/lib/services/api_client.dart` | create | HTTP client, cookie session |
| `flutter/xkhm_app/lib/services/*_service.dart` | create | Typed services per endpoint group |
| `flutter/xkhm_app/lib/models/*.dart` | create | DTOs mirroring API payloads |
| `flutter/xkhm_app/lib/screens/*.dart` | create | Login, Home, ClientArea, Invoices, Domains, Tickets, KB, Announcements, DomainChecker, ServerStatus |
| `flutter/xkhm_app/lib/widgets/*.dart` | create | Shared token-driven widgets |
| `src/Http/Controller/Api/*.php` | create | JSON controllers (reusing existing services) |
| `src/Http/Controller/Api/JsonResponse.php` | create | Uniform JSON envelope + error codes |
| `public/index.php` | modify | Register `/api/v1/*` routes |
| `tests/Integration/ApiContractTest.php` | create | Endpoint + payload contract tests |
| `tests/Playwright/test_flutter_*.py` | create | E2E against Flutter web build |
| `Makefile` | modify | `tokens`, `tokens-check`, `flutter-build`, `flutter-e2e` targets |
| `docs/FLUTTER.md` | create | Bootstrap, run, build, E2E instructions |

### API envelope (contract)

```json
{ "ok": true,  "data": { ... },  "error": null }
{ "ok": false, "data": null,    "error": { "code": "AUTH_INVALID", "message": "..." } }
```

Auth: `POST /api/v1/auth/login` with `{"email": "...", "password": "..."}` → sets
`PHPSESSID` cookie; subsequent calls reuse it. Reuses `Xkhm\Service\AuthService`
unchanged → WHMCS MD5/salted-MD5 semantics preserved in one place.

### Flutter project layout

```
flutter/xkhm_app/
├── pubspec.yaml
├── assets/design_tokens.json      (copied from repo root, for build-time reference)
├── lib/
│   ├── main.dart
│   ├── theme/{tokens.dart, lk_theme.dart}
│   ├── models/
│   ├── services/{api_client.dart, auth_service.dart, billing_service.dart, ...}
│   ├── screens/{login, home, client_area, invoices, domains, tickets, knowledgebase, announcements, domain_checker, server_status}.dart
│   └── widgets/{lk_button.dart, lk_card.dart, lk_app_bar.dart, lk_status_badge.dart, ...}
└── test/                          (widget + unit tests)
```

## Testing Strategy

- **Token pipeline:** `make tokens-check` — regenerate to temp dir, diff. Drift = fail.
- **API contract (PHPUnit integration):** every `/api/v1` endpoint — status code, envelope
  shape, and DB-row-count parity with the corresponding HTML page's data source.
- **API auth (PHPUnit integration):** login with valid/invalid credentials; session reuse;
  logout invalidates.
- **Flutter unit/widget tests:** `flutter test` — token constants match the JSON,
  each screen renders its empty/loading/error states, DTO parsing.
- **E2E (Playwright, T021 harness):** against `flutter run -d web-server`:
  login → home → client area → invoices → tickets → KB → announcements →
  domain checker → server status. Uses Flutter's semantics DOM (`flt-semantics`) and
  `SemanticsBinding` labels, since Flutter web paints to canvas.
- **Regression:** existing PHPUnit suite green; `make lint` green; Docker endpoints all 200.

## Verification Criteria

- [ ] `make tokens-check` green — CSS and Dart tokens provably match `design_tokens.json`
- [ ] No raw hex literal in any `flutter/xkhm_app/lib/**/*.dart` (enforced by grep gate)
- [ ] `make flutter-build` produces `flutter/xkhm_app/build/web`
- [ ] `flutter test` green
- [ ] `make test` (PHPUnit) green — no regression
- [ ] `make e2e` green — PHP backend suite
- [ ] `make flutter-e2e` green — Flutter web suite
- [ ] Every Flutter screen renders in pt-BR
- [ ] No SQL or password-hashing logic anywhere under `flutter/xkhm_app/lib/`

## Estimation

- Complexity: **high** (> 8h)
- Risk: medium
- Blocking dependencies: yes — T021 (Playwright harness) must be green first, since it
  provides the fixture/artifact infrastructure reused by the Flutter E2E.

> **Gate:** Stages A and B proceed immediately (low risk, high value, independently
> verifiable). Stage C build-out proceeds once T021 is green. Per the hostile analysis,
> the "replacement vs. supplement" decision is deferred to a follow-up ticket (T022)
> with production traffic data, and the operator is notified that the PHP pages remain
> live in the meantime.
