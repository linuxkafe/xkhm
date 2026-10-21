# xkhm — Flutter Application

The LINUXKAFE client application, built with Flutter (web, Android and iOS).

It talks to the **existing** PHP backend through the versioned JSON API
(`/api/v1`). The WHMCS database (`linuxkonect`) remains the single source of
truth: there is no SQL and no password-hashing logic in the Dart code.

---

## Architecture

```
┌──────────────────────────────┐        ┌───────────────────────────────┐
│  Flutter app                 │        │  PHP / Slim 4 backend         │
│  ┌────────────────────────┐  │  HTTP  │  ┌─────────────────────────┐  │
│  │ screens/  widgets/     │  │───────►│  │ /api/v1/*  (JSON)       │  │
│  │ services/ models/      │  │◄───────│  │ src/Http/Controller/Api  │  │
│  │ theme/tokens.dart      │  │        │  └───────────┬─────────────┘  │
│  └────────────────────────┘  │        │              │ PDO            │
└──────────────────────────────┘        │  ┌───────────▼─────────────┐  │
                                          │  │ linuxkonect (MySQL)    │  │
                                          └─────────────────────────┘  │
```

- **Session**: the same `PHPSESSID` cookie the website uses. There is no
  separate token system, so the WHMCS authentication semantics live in
  exactly one place (the PHP backend).
- **Web build**: served from the *same origin* as the API (`/app/`), so the
  browser owns the cookie natively — no CORS, no manual cookie handling.
- **Native builds**: talk to the API cross-origin; the API sends the CORS
  headers and the app replays the cookie explicitly.

---

## Design tokens

The colour scheme comes from `http://www.linuxkafe.com` and lives in **one**
file: `assets/design_tokens.json` (repository root).

```bash
make tokens        # regenerate tokens.css + tokens.dart
make tokens-check  # fail if the generated files are stale
```

`tools/sync_tokens.py` generates:

| Output | Consumer |
|--------|----------|
| `public/templates/default/css/tokens.css` | the PHP/CSS front-end |
| `flutter/xkhm_app/lib/theme/tokens.dart` | the Flutter application |

`tools/check_no_hardcoded_colors.py` fails the build if a raw hex literal
appears in any Dart source — the palette cannot silently fork.

---

## Running

### Web

```bash
make flutter-build          # builds flutter/xkhm_app/build/web
docker compose up -d        # mounts the build at http://localhost:8083/app/
```

The build is mounted into the PHP container, so the application and the API
share an origin.

### Development server

```bash
cd flutter/xkhm_app
flutter run -d chrome --dart-define=API_BASE_URL=http://localhost:8083
```

`API_BASE_URL` is empty by default, meaning *same origin*. Native builds
must pass an absolute URL:

```bash
flutter build apk --release --dart-define=API_BASE_URL=https://api.linuxkafe.com
```

---

## Testing

```bash
make flutter-test    # Flutter unit + widget tests (fast, no browser)
make flutter-e2e     # Playwright against the built web bundle
```

The Playwright suite reads Flutter's **accessibility tree** (`flt-semantics`),
because Flutter renders to a canvas and the widget tree is not in the DOM.
The tree is materialised by activating Flutter's hidden "Enable
accessibility" placeholder.

Because the app and the API share an origin, the E2E suite performs a **real
login** with real credentials and asserts on the real data coming from the
`linuxkonect` tables.

---

## Screens

| Screen | API endpoints |
|--------|---------------|
| Login | `POST /api/v1/auth/login`, `GET /api/v1/auth/session` |
| Painel (dashboard) | `GET /api/v1/client/summary` |
| Faturas | `GET /api/v1/client/invoices` |
| Tickets | `GET /api/v1/client/tickets`, `POST /api/v1/client/tickets` |
| Base de Conhecimento | `GET /api/v1/knowledgebase`, `.../categories` |
| Anúncios | `GET /api/v1/announcements` |
| Procurar Domínio | `GET /api/v1/domains/check` |
| Status do Servidor | `GET /api/v1/serverstatus` |

The UI is pt-BR throughout.

---

## Project layout

```
flutter/xkhm_app/
├── lib/
│   ├── main.dart            # app entry, providers, routing
│   ├── models/models.dart   # DTOs mirroring the API payloads
│   ├── services/
│   │   ├── api_client.dart  # HTTP client + session cookie handling
│   │   └── session.dart     # authentication state
│   ├── screens/             # one file per screen
│   ├── theme/
│   │   ├── tokens.dart      # GENERATED — do not edit
│   │   └── lk_theme.dart    # Material 3 theme built from the tokens
│   └── widgets/lk_widgets.dart
├── assets/fonts/            # Inter + Poppins, bundled (no font CDN)
├── test/                    # unit + widget tests
└── web/flutter_bootstrap.js # pins CanvasKit to the local copy
```
