---
ticket: T022
phase: learn
status: done
created: 2026-10-21
tier: heavy
---

# T022 — Learn: Flutter Client Application

## What was built

A Flutter application (web + Android + iOS) for the LINUXKAFE client area,
consuming a new versioned JSON API (`/api/v1`) over the existing
`linuxkonect` database. Plus the design-token pipeline that keeps the PHP
front-end and the Flutter app on one brand definition.

## Insights captured

| Shadow doc | Insight |
|------------|---------|
| `SD-CI-001` | Flutter web + Playwright requires the semantics tree; the typing/click mechanics and the race condition |
| `SD-CI-002` | E2E over the real database is the only thing that catches WHMCS schema drift |
| `SD-META-014` | A token *generator* makes brand drift detectable; two hand-written lists do not |
| `SD-META-015` | Serving the app next to the API removes CORS and keeps one auth implementation |

## The three decisions that mattered

### 1. The backend became API-first, not HTML-first

The plan's hostile analysis flagged this as the coupling risk (FAILURE MODE 1):
a Flutter client cannot consume HTML, so the ticket had to include a JSON
layer whether we liked it or not. Making it explicit turned an accident into
a design — and the API is now the durable asset, the Flutter app merely its
first consumer. The WHMCS-compatible `/api.php` was left untouched.

### 2. The session was not reinvented

The hardest constraint was "fully compatible with the current database". The
naive reading is "build a new auth system for the app". The better reading
turned out to be "the auth system already exists — put the app next to it".
Serving the Flutter build at `/app/` means the browser owns the cookie, no
CORS is needed for the web build, and there is exactly one implementation of
the WHMCS 5.3.9 MD5 / salted-MD5 semantics.

### 3. The test harness came before the client

T021 (Playwright) was planned and delivered first. It found fourteen
production defects — including three that were *fatal errors on pages only
reachable with a session*, and two that silently corrupted every pt-BR
accent in the database. Building the Flutter client first would have
stacked a second untested surface on top of those.

## What surprised us

- **The `flutter_localizations` omission crashed the entire app.** Declaring
  `locale: Locale('pt','BR')` without the delegates makes every Material
  widget throw "No MaterialLocalizations found" — a null-check error deep in
  the framework with a stack that points nowhere useful. It only surfaced
  when the app was served same-origin; before that, the API failure masked
  it.
- **Flutter web does not use the CanvasKit it bundles.** It fetches it from
  `gstatic.com` by default, which the deployment's Content-Security-Policy
  blocks — and would have made the app depend on an external host at
  runtime. `web/flutter_bootstrap.js` pins it to the local copy.
- **A hard-coded `match` result in `ApiController` cost the API its
  content-type.** `match (...) { ... };` as a statement discards the value.
  The JSON body was right; the `Content-Type` header was never set.
- **Two hand-written token lists would have drifted.** The first version of
  the plan had `tokens.css` and `tokens.dart` as separate committed files.
  The hostile analysis killed it in favour of a generator — and the guard
  script found 16 hard-coded literals the moment it was switched on.

## Deferred, deliberately

- The Flutter admin panel (a separate ticket).
- Payment capture (the invoice screen is read-only).
- Retiring the PHP front-end — deferred to **T023**, which needs production
  traffic data. The PHP pages remain live and are covered by 132 E2E tests.
- Non-pt-BR locales.

## Follow-ups worth a ticket

- The `/api/v1` responses are not yet versioned in a stable way — the
  contract is implicit in the DTOs. A contract test that asserts the exact
  JSON shape would harden it.
- `SslService::getCertificateInfo()` returns simulated data. The live
  service should parse the actual certificate.
- The Flutter app has no offline mode; the `LkNetworkException` path shows
  an error but offers no retry on the login screen.
