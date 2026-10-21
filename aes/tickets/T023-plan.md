---
ticket: T023
phase: plan
status: done
created: 2026-10-21
tier: heavy
verdict: pass
requires:
  - aes/tickets/T022-verify.md
  - aes/tickets/T023.md
produces:
  - aes/tickets/T023-plan.md
blocked_by: ''
---

# T023 — Plan: Dark Mode Automático + Compatibilidade Mobile

## Reconnaissance Summary

| Finding | Detail |
|---------|--------|
| Token pipeline | `assets/design_tokens.json` → `tools/sync_tokens.py` → `tokens.css` + `tokens.dart`; `make tokens-check` detecta deriva |
| Dart tokens | `LkColors` (8 cores), `LkSemantic` (11 cores), `LkFonts`, `LkLayout` — **todos `static const`, sem notion de modo** |
| Flutter theme | `LkTheme.light()` — um único `ThemeData`, `ColorScheme.fromSeed(brightness: light)` |
| Flutter `main.dart` | `theme: LkTheme.light()`, sem `darkTheme`, sem `themeMode` |
| CSS | `whmcs.css` tem **um** media query (`max-width: 850px`); `bootstrap.css` tem outro (`768px`) |
| Viewport | `Layout.php` e o middleware injectam `width=device-width, initial-scale=1` — sem `viewport-fit=cover` |
| Safe areas | Nenhuma referência a `env(safe-area-inset-*)` em todo o CSS |
| Alvos de toque | `.btn` tem `padding: 8px 16px` — abaixo dos 44 px de recomendação |
| Flutter mobile | `HomeShell` já usa `NavigationBar` abaixo de 900 px, mas só com `take(4)` — **3 das 7 secções ficam inacessíveis** |
| Flutter safe area | `LoginScreen` usa `SafeArea`; as restantes ecras não |
| Flutter teclado | `NewTicketSheet` usa `MediaQuery.viewInsetsOf` — o único ponto que trata o teclado |
| `android/` | Gerado pelo `flutter create`, sem `windowSoftInputMode` |
| `ios/` | Gerado pelo `flutter create`, sem `UIRequiresFullScreen` / dark-mode capability |

### Gap crítico encontrado

`LkColors` é uma classe de constantes. Para suportar dois modos há duas
opções: **duas classes** (`LkColors` / `LkColorsDark`) ou **uma classe com
resolução por modo**. A escolha afecta a API de todos os widgets.

---

## Two-Agent Analysis (heavy tier)

### Critic Phase

```
## Como se fosse uma criança
Imagine que a marca tem duas camisolas: uma clara e outra escura. Hoje
só existe a clara, e está escrita num papel que tanto o site como a
aplicação copiam. Para ter as duas camisolas, o papel tem de descrever
as duas — e as duas camisolas têm de ser feitas com o *mesmo* papel, senão
a marca deixa de ser a mesma à noite.

E o telemóvel: um botão pequeno demais é um botão que se falha a tocar
com o dedo. E um ecrã com o "bico" (notch) tapado pelo cabeçalho é um
ecrã com conteúdo por baixo do bico.

## Como se fosse um especialista
The current token model is a flat namespace of compile-time constants with
no mode dimension. Dark mode requires either a parallel constant set
(`LkColorsDark.primary`) or a mode-resolved accessor
(`LkColors.of(context).primary`). The choice propagates into every widget.

[FAILURE MODE 1] — The two clients drift: the CSS gets a hand-written
`@media (prefers-color-scheme: dark)` block while the Dart gets a
hand-written `LkColorsDark` class. The generator is bypassed for the dark
half, and the brand now has two definitions again — precisely the failure
SD-META-014 documented.
Mechanism: the generator currently emits a flat `:root { }`. Supporting
dark mode means the generator must emit a *second* block, and any hand-
written shortcut around the generator reintroduces the drift.

[FAILURE MODE 2] — Widgets that resolve the colour at build time cannot
follow a mode change: `LkCard` uses `LkColors.bgCard` directly. If the
mode flips at runtime (the OS theme changes while the app is open), a
build-time constant is stale. Flutter's own `Theme.of(context)` exists
precisely to avoid this.
Mechanism: `ColorScheme` is already mode-aware; the app bypasses it by
reading raw constants. The fix is to route widget colours through the
`ThemeData`, not through the token constants.

[FAILURE MODE 3] — Mobile "compatibility" is not a checklist of viewport
sizes. The real failure modes are: (a) content under the notch / gesture
bar, (b) the virtual keyboard covering the focused field, (c) touch
targets below the platform minimum, (d) tables that force a horizontal
scroll, (e) the bottom navigation offering only a subset of the
destinations. Optimising for "it looks right at 390px" fixes none of them.
Mechanism: the current `HomeShell` shows 4 of 7 destinations on mobile —
the other 3 are simply unreachable, which no viewport test would catch.

[ASSUMPTION] If false — "dark mode" means an in-app toggle rather than
following the system: the consequence is that the app needs a preference
store, a settings screen, and a way to express "system / light / dark"
(rather than a boolean). Mitigation: the token structure supports all
three; the *UI* for the toggle is explicitly deferred to T024, and the
design keeps `ThemeMode.system` as the only wired behaviour.
Validation: the operator said "dark mode automático" — automatic implies
following the system.

[ALTERNATIVE FRAMING] Instead of "add a dark palette", frame it as
"the palette becomes a function of the mode": `palette(mode) → colours`.
Both clients then ask the same question and get the same answer. In CSS
that is two `:root`-scoped blocks selected by a media query; in Dart it is
a `ThemeData` per brightness. The tokens file stores *both* palettes, and
the generator emits both representations.

## Porquê? ×5
1. Why does dark mode need a token change? → Because the palette is
   compile-time constants; a mode dimension cannot be expressed without it.
2. Why must the generator emit the dark half? → Because a hand-written
   dark block re-creates the two-sources problem SD-META-014 solved.
3. Why route widget colours through `Theme.of(context)`? → Because only
   the theme is rebuilt when the OS mode changes; a constant is not.
4. Why is a mobile checklist insufficient? → Because the failures are
   structural (unreachable destinations, covered fields), not visual.
5. Why defer the manual toggle? → Because "automatic" is the requirement;
   the toggle is a separate, larger decision (storage, precedence rules).

What are we optimizing that we shouldn't be?
We are optimising for "the app looks right in dark mode". We should be
optimising for "there is exactly one definition of the brand per mode, and
both clients obey it" — the same lesson as SD-META-014, applied to a
dimension the pipeline did not yet have.
```

### Implementor Phase

```
## Como se fosse uma criança
O papel das cores passa a ter duas colunas: a de dia e a de noite. A
máquina que copia o papel para o site e para a aplicação copia as duas.
E na aplicação, cada cor passa a ser pedida ao tema (que sabe se é dia ou
noite) em vez de estar escrita à mão no widget.

No telemóvel, o botão grows até ter 44 pixels, o conteúdo passa a ser
empurrado para baixo do bico, e a lista de secções passa a caber toda —
numa gaveta, para não ficar só 4 de 7.

## Como se fosse um especialista
Three deliverables, again staged with a green gate after each.

(D1) The palette becomes mode-aware, end to end.

`assets/design_tokens.json` gains a `dark` section holding the dark
values of the same eight brand colours plus the semantic ones. The
structure mirrors the light section exactly, so the generator can emit
both with one code path.

```json
{
  "colors":     { "color-primary": "#f8b400", ... },
  "darkColors": { "color-primary": "#f8b400", "color-bg": "#141414", ... }
}
```

`tools/sync_tokens.py` then emits:

*CSS* — two blocks:
```css
:root { --color-primary: #f8b400; ... }          /* light */
@media (prefers-color-scheme: dark) {
  :root { --color-primary: #f8b400; --color-bg: #141414; ... }
}
```
plus `color-scheme: light dark;` on `:root` so the browser also themes
the scrollbars and form controls.

*Dart* — a parallel constant set plus a resolver:
```dart
abstract final class LkColors {
  static const Color primary = Color(0xFFF8B400);
  // ... light values (unchanged)

  static const Color darkPrimary = Color(0xFFF8B400);
  // ... dark values
}
```
The existing names keep their meaning, so **no widget has to change** to
keep working — the migration to theme-resolved colours is then a separate,
mechanical step (D3) that can be verified one widget at a time.

(D2) Flutter theme: light + dark, driven by the system.

```dart
MaterialApp(
  theme: LkTheme.light(),
  darkTheme: LkTheme.dark(),
  themeMode: ThemeMode.system,
)
```

`LkTheme.dark()` is built from the same `ThemeData` recipe with the dark
token values substituted — one builder, two palettes. That is the Dart
equivalent of the CSS media query.

(D3) Widget colours resolve through the theme, not through constants.

`LkCard`, `LkStatusBadge`, `LkStatTile`, `LkSectionTitle` and the shared
text styles read `Theme.of(context).colorScheme` / `theme.textTheme`
instead of `LkColors.*`. Only the *brand* colours (primary, header) stay
as constants, because they do not change between modes.

This is what makes a runtime mode change actually repaint the app.

(D4) Mobile hardening.

*CSS*
- `viewport-fit=cover` + `env(safe-area-inset-*)` padding on the header
  and the footer
- `.btn`, `a`, and form controls get a ≥ 44 px touch target
- `.table` becomes a horizontally scrollable wrapper below 768 px rather
  than forcing the page to scroll
- `overflow-wrap: break-word` on long values (domains, e-mails)

*Flutter*
- `SafeArea` around the shell and every scrolling screen
- the virtual keyboard: `Scaffold(resizeToAvoidBottomInset: true)` and
  `MediaQuery.viewInsetsOf` where a form is at the bottom of the screen
- **the navigation gap**: on a narrow screen the `NavigationBar` shows
  4 of 7 destinations, making 3 unreachable. Fix: a `NavigationDrawer`
  holding all 7, with the `NavigationBar` as the primary 4 and a
  "mais" entry opening the drawer
- `android:windowSoftInputMode=adjustResize` in the manifest

## ADDRESSING FAILURE MODE 1 (client drift)
Mitigation: **the generator is the only writer of both halves.** There is
no hand-written `@media (prefers-color-scheme: dark)` block anywhere; the
check is structural — `tools/check_no_hardcoded_colors.py` already refuses
a hex literal in any Dart source, and the CSS is verified by a Playwright
assertion that the dark block is present and that the values match the
JSON. If someone hand-writes a dark rule, the `make tokens-check` diff
fails.

## ADDRESSING FAILURE MODE 2 (stale build-time colours)
Mitigation: widget colours move to `Theme.of(context)`. The `LkCard` and
`LkStatusBadge` receive their colours from the `ThemeData`, which Flutter
rebuilds when `MediaQuery.platformBrightnessOf` changes. A Playwright test
toggles the emulated colour scheme at runtime and asserts that the
rendered background changed — which is exactly the assertion that fails if
any widget kept a raw constant.

## ADDRESSING FAILURE MODE 3 (mobile as a checklist)
Mitigation: the acceptance criteria are **behavioural**, not visual:
- AC5 asserts `document.documentElement.scrollWidth <= clientWidth` at
  360 px — a real "no horizontal scroll" assertion, for every page;
- AC6 asserts every interactive element's bounding box is ≥ 44 px;
- AC7 asserts the Flutter content is inside the safe area;
- AC8 asserts that all seven destinations are *reachable* on a narrow
  viewport — a functional assertion, not a screenshot.

## ADDRESSING ASSUMPTION (automatic vs. manual toggle)
Resolution: the token structure supports three states (system / light /
dark) because the palette is a function of the mode, but only
`ThemeMode.system` is wired. The manual toggle — with its storage and its
precedence rules — is T024. The operator's words were "dark mode
automático", which is exactly what is delivered.

## ADDRESSING ALTERNATIVE FRAMING (palette as a function of the mode)
Accepted and adopted: the JSON holds one palette per mode, the generator
emits one representation per client per mode, and the Flutter theme is
built by one builder parameterised by the palette. "Automatic" and
"manual" then differ only in which `ThemeMode` is passed — a one-line
change when T024 arrives.

## [SOLUTION PROPOSAL]
Stage A (tokens + generator) → Stage B (Flutter dark theme) →
Stage C (theme-resolved widgets) → Stage D (mobile hardening, CSS) →
Stage E (mobile hardening, Flutter) → Stage F (tests).

## Porquê? ×5
1. Why Stage A first? → Both clients consume the tokens; changing the
   palette later would mean re-touching every widget.
2. Why keep the light constants' names? → 60+ widget references stay
   valid; the migration becomes reviewable in small steps.
3. Why move widgets to `Theme.of(context)`? → Only the theme is rebuilt
   on a mode change; a constant is not.
4. Why the drawer for the navigation gap? → Three of seven destinations
   were unreachable on mobile — a functional hole, not a cosmetic one.
5. Why behavioural acceptance criteria? → "Looks right" cannot be
   asserted; "no horizontal scroll" and "all destinations reachable" can.

What are we optimizing that we shouldn't be?
We are optimising for "the app looks good in dark mode". We should be
optimising for "the brand has exactly one definition per mode, both
clients obey it, and the mobile experience has no unreachable functionality".
```

---

## Hostile Analysis (ticket level)

```
ASSUMPTIONS I AM MAKING:
- [KNOWN]    "Dark mode automático" means following the system preference —
  evidence: the word "automático". The manual toggle is T024.
- [KNOWN]    The system theme can change at runtime (Android/iOS send a
  `didChangePlatformBrightness`; the web sends a `prefers-color-scheme`
  change event) — so the theme must be rebuildable, not just selectable.
- [INFERRED] The operator's "máxima compatibilidade mobile" includes the
  PHP site, not only the Flutter app — evidence: the site is what real
  clients use today, and the request is not scoped to the app.
- [ASSUMED]  The brand's yellow (#f8b400) and dark header (#2d2d2d) stay
  the same in dark mode — impact if false: the dark palette would need
  brand-owner input; mitigated by keeping the primary unchanged and
  deriving the rest.
- [UNKNOWN]  Whether the operator wants the *whole* site in dark mode or
  only the client area. Impact: low — a single media query covers the
  whole site, which is the more useful behaviour.

WHAT WAS NOT SPECIFIED (that matters):
- Which surfaces exactly → resolved: the site, the app, and the API's
  error pages (which are HTML).
- Whether a manual override is needed → resolved: T024.

ALTERNATIVES NOT CHOSEN:
- Option A: filter/invert the light palette programmatically — rejected:
  inverting yellow gives an unreadable colour, and the result is not
  a designed palette.
- Option B: keep only the app in dark mode — rejected: the operator asked
  for the product, and the site is the primary surface today.
- Option C: a runtime CSS theme switcher in JS — rejected: the CSS
  media query does it with zero JavaScript, zero flash, and works with
  JS disabled.

RISKS AND SIDE EFFECTS:
- Contrast regression in dark mode — mitigated by choosing the dark
  palette so that text/background pairs keep ≥ 4.5:1, and by adding a
  contrast assertion to the Playwright suite.
- The Flutter widgets that still use raw constants after Stage C would
  not repaint on a mode change — mitigated by the runtime-toggle test.
- `env(safe-area-inset-*)` is 0 on desktop, so the desktop layout is
  unaffected — the risk is only a *visual* shift on notched devices.
- The dark palette's exact values are a design decision; they are
  recorded in the token file and reviewed in the diff.

COST OF BEING WRONG: medium — the work is reversible (the token file
holds both palettes) and each stage is independently shippable.

SCOPE BOUNDARY:
In scope: dark mode for the site and the app, mobile hardening for both,
the tests. Out of scope: a manual theme toggle, other languages, offline
PWA, push notifications, any schema change.

INVITATION FOR CONTRADICTION:
The most likely flaw: that "dark mode" is a *cosmetic* requirement while
"mobile compatibility" is a *functional* one, and that treating them as
one ticket lets the cosmetic half dominate. Mitigation: the acceptance
criteria are weighted towards the functional half (5 of 10 criteria are
behavioural assertions about scroll, touch targets, safe areas and
reachable destinations), and the dark palette is generated rather than
designed from scratch, which bounds its cost.
```

## Technical Approach

**Chosen:** the palette becomes a function of the mode, generated from one
source; the Flutter theme follows the system; widget colours resolve
through the theme; both clients are hardened for mobile with behavioural
assertions.

**What will NOT be implemented:** a manual theme toggle (T024), a
theme-preview switcher, per-surface dark variants, offline support.

### Affected files

| File | Operation | Description |
|------|-----------|-------------|
| `assets/design_tokens.json` | modify | Add the `darkColors` / `darkSemantic` sections |
| `tools/sync_tokens.py` | modify | Emit the dark CSS block + the dark Dart constants |
| `public/templates/default/css/tokens.css` | modify (generated) | `:root` light + `@media (prefers-color-scheme: dark)` |
| `flutter/xkhm_app/lib/theme/tokens.dart` | modify (generated) | `LkColors` light + dark, `LkSemantic` light + dark |
| `flutter/xkhm_app/lib/theme/lk_theme.dart` | modify | One builder, two palettes; `LkTheme.dark()` |
| `flutter/xkhm_app/lib/main.dart` | modify | `darkTheme` + `themeMode: ThemeMode.system` |
| `flutter/xkhm_app/lib/widgets/lk_widgets.dart` | modify | Colours from `Theme.of(context)` |
| `flutter/xkhm_app/lib/screens/*.dart` | modify | `SafeArea`, keyboard insets, drawer navigation |
| `flutter/xkhm_app/android/app/src/main/AndroidManifest.xml` | modify | `windowSoftInputMode=adjustResize` |
| `public/templates/default/css/whmcs.css` | modify | Safe areas, 44 px targets, responsive tables |
| `public/templates/default/css/bootstrap.css` | modify | Same for the base grid |
| `src/Http/View/Layout.php` | modify | `viewport-fit=cover`, safe-area padding |
| `src/Http/Middleware/StylesheetMiddleware.php` | modify | `viewport-fit=cover` in the injected meta |
| `tests/Playwright/test_dark_mode.py` | create | Dark-mode + mobile assertions, both clients |
| `tests/Playwright/test_mobile.py` | create | Viewport, scroll, touch-target assertions |
| `flutter/xkhm_app/test/theme_test.dart` | create | Both themes render, colours differ |
| `Makefile` | modify | `make e2e-dark`, `make e2e-mobile` |

### Dark palette (proposed)

| Token | Light | Dark | Rationale |
|-------|-------|------|-----------|
| `--color-primary` | `#f8b400` | `#f8b400` | the brand yellow stays — it is the identity |
| `--color-text` | `#212121` | `#e8e8e8` | inverted body text |
| `--color-text-light` | `#555555` | `#a0a0a0` | secondary text, still ≥ 4.5:1 on `#141414` |
| `--color-bg` | `#ffffff` | `#141414` | the page background |
| `--color-bg-card` | `#ffffff` | `#1e1e1e` | cards sit one step above the background |
| `--color-header` | `#2d2d2d` | `#0d0d0d` | the header goes deeper in dark |
| `--color-border` | `#e0e0e0` | `#333333` | borders need to be visible but quiet |
| `--color-link` | `#0056b3` | `#66b2ff` | a link blue that holds contrast on dark |

## Testing Strategy

- **Tokens:** `make tokens-check` — the generated CSS and Dart match the JSON
- **CSS dark mode:** Playwright with `color_scheme="dark"` — the computed
  `background-color` of `<body>` is dark and the `color` is light, for every
  public page
- **CSS mobile:** Playwright at 360×640 — `scrollWidth <= clientWidth` and
  every interactive element ≥ 44 px
- **Flutter dark mode:** Playwright with `color_scheme="dark"` on `/app/`,
  plus a runtime toggle assertion (the page must repaint without a reload)
- **Flutter mobile:** Playwright at 390×844 — all seven destinations are
  reachable
- **Widget tests:** both `LkTheme.light()` and `LkTheme.dark()` build
  without error and differ in `scaffoldBackgroundColor`
- **Regression:** the full existing suite stays green

## Verification Criteria

- [ ] `make tokens-check` green
- [ ] `make test` green (no regression)
- [ ] `make lint` green
- [ ] `make e2e` green (light + dark + mobile)
- [ ] `make flutter-test` green
- [ ] AC1–AC10 evidenced by an automated assertion each

## Estimation

- Complexity: high (> 8h)
- Risk: medium
- Blocking dependencies: no (T022 is done)
