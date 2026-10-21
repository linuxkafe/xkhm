---
ticket: T023
phase: verify
status: done
created: 2026-10-21
tier: heavy
verdict: pass
---

# T023 — Verify: Dark Mode Automático + Compatibilidade Mobile

## Stage gates

| Stage | Deliverable | Gate | Result |
|-------|-------------|------|--------|
| A | Paleta por modo no token pipeline | `make tokens-check` in-sync; chaves da paleta escura validadas contra a clara | **pass** |
| B | Tema Flutter escuro | `ThemeMode.system`; ambos os temas constroem | **pass** |
| C | Cores dos widgets via o tema | nenhum literal de cor fora dos tokens | **pass** |
| D | CSS: safe areas, alvos de toque, tabelas, breakpoints | sem scroll horizontal a 360 px; alvos ≥ 44 px | **pass** |
| E | Flutter: SafeArea, gaveta, teclado | as 7 secções alcançáveis a 390 px | **pass** |
| F | Testes | 66 asserções novas + 20 de contraste | **pass** |

## Gates

| Gate | Command | Result |
|------|---------|--------|
| PHP unit + integration | `make test` | **61 passed**, 123 assertions |
| PHP static analysis | `make lint` | **0 errors** (PHPStan level 5) |
| Design tokens | `make tokens-check` | **in sync**, sem cores escritas à mão (Dart **e** CSS) |
| Playwright (todos) | `make e2e` | **225 passed** |
| Flutter unit/widget | `make flutter-test` | **40 passed** (20 novos de contraste) |
| Flutter static analysis | `flutter analyze` | **0 issues** |
| Flutter web build | `make flutter-build` | **built** |

## Acceptance criteria

| AC | Criterion | Evidence | Result |
|----|-----------|----------|--------|
| AC1 | O site segue `prefers-color-scheme`, sem JavaScript | `test_page_follows_the_dark_preference` (10 rotas) + `test_dark_mode_needs_no_javascript` | **pass** |
| AC2 | A aplicação segue o tema do sistema | `themeMode: ThemeMode.system`; `test_flutter_follows_the_dark_preference` | **pass** |
| AC3 | A paleta escura vem do mesmo ficheiro de tokens | `assets/design_tokens.json` → `sync_tokens.py`; o validador recusa chaves divergentes | **pass** |
| AC4 | Nenhum literal hex nos fontes Dart | `tools/check_no_hardcoded_colors.py` — Dart **e** CSS | **pass** |
| AC5 | Sem scroll horizontal a 360 px | `test_page_does_not_scroll_sideways` (10 rotas × 3 viewports) | **pass** |
| AC6 | Alvos interactivos ≥ 44×44 px | `test_interactive_elements_have_a_tappable_size` (10 rotas) | **pass** |
| AC7 | Safe areas e teclado virtual respeitados | `test_the_header_respects_the_safe_area`, `test_the_footer_respects_the_safe_area`; `SafeArea` no shell e nas gavetas; `viewInsets` no formulário | **pass** |
| AC8 | As 7 secções alcançáveis em ecrã estreito | `test_flutter_drawer_reaches_every_destination` | **pass** |
| AC9 | Playwright passa em dark e em mobile, para os dois clientes | 66 asserções em `test_dark_mode.py` + 4 em `test_flutter_app.py` | **pass** |
| AC10 | Todos os portões verdes | tabela acima | **pass** |

## Contrast (WCAG 2.1, medido)

| Par | Modo claro | Modo escuro | Requisito |
|-----|-----------|-------------|-----------|
| texto do AppBar sobre o cabeçalho | 8.6:1 | 12.4:1 | 4.5:1 |
| texto do corpo sobre o fundo | 16.1:1 | 13.6:1 | 4.5:1 |
| botão primário (sobre o amarelo) | 8.83:1 | 9.55:1 | 4.5:1 |
| texto secundário sobre o fundo | 7.0:1 | 6.6:1 | 4.5:1 |
| entrada seleccionada da gaveta | 8.6:1 | 9.4:1 | 4.5:1 |
| chip seleccionado | 8.8:1 | 8.9:1 | 4.5:1 |

As asserções vivem em `flutter/xkhm_app/test/theme_test.dart` — 20 asserções
que cobrem cada par de cor do tema nos dois modos. Um par que desça abaixo de
4.5:1 falha o build.

## Defeitos encontrados na revisão e corrigidos

| # | Defeito | Severidade |
|---|---------|------------|
| 1 | Título e acções do AppBar invisíveis no modo claro (1.17:1) | **blocker** |
| 2 | Entrada seleccionada da gaveta amarelo sobre amarelo (0 pixels) | **blocker** |
| 3 | Texto sobre superfícies amarelas a 1.49:1 no modo escuro | **blocker** |
| 4 | Palavra-marca do NavigationRail invisível no modo escuro | major |
| 5 | `.btn-success` branco sobre verde pálido (1.58:1) | major |
| 6 | A barra inferior iluminava "Painel" para uma secção da gaveta | major |
| 7 | Rótulo do chip seleccionado a 1.41:1 no modo escuro | major |
| 8 | Sombra dos cartões sempre com a paleta clara | minor |
| 9 | "Disponível"/"Registado" com badge cinzento | minor |
| 10 | Texto secundário não vinha do token | minor |
| 11 | O guarda de cores não cobria o CSS | minor |
| 12 | `Colors.white` escapava ao guarda | minor |
| 13 | Hex de 3 dígitos aceite no CSS mas não no Dart | minor |
| 14 | Breakpoints do CSS divergiam dos tokens | minor |
| 15 | Safe-area insets aplicadas só ao cabeçalho | minor |
| 16 | `.table-scroll` era CSS morto | minor |
| 17 | `manifest.json`/`index.html` com o azul do template Flutter | minor |
| 18 | `LkFonts` gerado e não usado | minor |

## Known trade-off (accepted, with reason)

`h1..h4` use `--color-primary` (#f8b400) sobre `--color-bg` (#ffffff) —
**1.82:1**, abaixo do 3:1 que a WCAG exige para texto grande. Isto é
**deliberado**: o operador exigiu explicitamente "use the same colour
scheme" do linuxkafe.com, onde os títulos são amarelos. Alterar a cor dos
títulos seria uma divergência visível da marca. O token `--color-on-primary`
existe para quem quiser tomar essa decisão; e o contraste do *texto do
corpo* (16.1:1) e dos controlos interativos (8.8:1) está conforme.

## Conclusion

**PASS.** O sistema segue a preferência do sistema nos dois clientes, sem
JavaScript no site; a paleta por modo tem uma única fonte; e a compatibilidade
mobile é verificada por asserções comportamentais (scroll, alvos de toque,
safe areas, secções alcançáveis) e não por capturas de ecrã.
