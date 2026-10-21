---
ticket: T023
phase: learn
status: done
created: 2026-10-21
tier: heavy
---

# T023 — Learn: Dark Mode Automático + Compatibilidade Mobile

## What was built

A palette that is a **function of the mode**, generated from one source for
both clients; a Flutter theme that follows the system; mobile hardening for
the site and the app; and 70 new automated assertions that hold all of it
in place.

## Insights captured

| Shadow doc | Insight |
|------------|---------|
| `SD-META-016` | The palette is a function of the mode, not a file — and the cascade order silently kills dark mode |
| `SD-CI-003` | Mobile compatibility is functional, not visual — three of seven sections were unreachable |

## The three decisions that mattered

### 1. The mode became a dimension of the token source, not a new file

The first instinct was `tokens-dark.css` + `LkColorsDark`. That is
SD-META-014's mistake again. The token source now holds a palette per mode
with **identical keys**, and the generator refuses a palette whose keys
diverge. One writer, one truth, two modes.

### 2. The token file has to be the *only* definition of the custom properties

The `whmcs.css` still had its own `:root` block from before. Because it is
loaded *after* `tokens.css` and has the same specificity, it silently
overrode the `@media (prefers-color-scheme: dark)` block — the site's dark
mode did not work at all until that duplicate was removed. The lesson is
sharper than "don't duplicate the tokens": **when the palette is generated,
the generated file must be the only source of the custom properties.**

### 3. Widget colours had to move into the theme

`LkColors.bgCard` is a compile-time constant. A constant does not repaint
when the OS switches to dark mode. Only `ThemeData` is rebuilt. Moving the
widgets onto `Theme.of(context)` is what makes the app actually follow the
system rather than merely offering a dark theme nobody reaches.

## What surprised us

- **Deleting `bootstrap.css` was a correctness fix, not a cleanup.** It was
  a leftover from the original WHMCS with the *old blue* palette
  (`#2a3b5c`) and a duplicate grid — a second source of truth that the
  token pipeline could not govern.
- **The review found a 1.17:1 contrast that no test could see.** The AppBar
  title used `onSurface` on the header colour. It looked fine in a
  screenshot on a bright monitor and was invisible on a real screen. It
  took a *pixel-level* review to find, and a *contrast assertion* to keep
  it fixed.
- **The selected drawer entry rendered zero pixels.** Material 3 resolves a
  selected `ListTile`'s label colour to `primary` — and the selected
  background was also `primary`. Yellow on yellow. The accessibility tree
  was complete; the drawing was not.
- **The bottom bar highlighted the wrong destination.** `indexOf` returned
  `-1` for a drawer-only section and `clamp(0, 3)` turned that into "0" —
  so the bar claimed "Painel" was selected while a different screen was
  showing. `NavigationBar` accepts `-1`; it just has to be passed.

## Deferred, deliberately

- A manual theme toggle — **T024**. The palette structure already supports
  "system / light / dark"; only the storage and the precedence rules are
  missing, and the operator asked for *automatic*.
- Per-surface dark variants (a dark *login* distinct from a dark
  *dashboard*). The single palette is enough for now.
- A PWA offline shell. The manifest is now brand-correct, but the service
  worker is still Flutter's default.

## Follow-ups worth a ticket

- The `h1..h4` headings are the brand yellow on white (1.82:1, below the
  3:1 that WCAG asks of large text). It was kept because the operator
  explicitly asked for the linuxkafe.com colour scheme; the
  `--color-on-primary` token is there for whoever decides otherwise.
- The API contract is still asserted through the UI rather than by shape.
- `SslService::getCertificateInfo()` still returns simulated data.
