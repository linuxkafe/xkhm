---
ticket: T047
phase: verify
status: done
created: 2026-10-22
verdict: pass
requires:
  - aes/tickets/T047-plan.md
---

# T047 — Verify: A interface de 2026 — o tipo de letra e o logótipo

## Gates

| Gate | Command | Result |
|------|---------|--------|
| PHPUnit | `make test` | **208 tests, 720 assertions** — green |
| PHPStan (nível 5) | `make lint` | **0 erros** |
| Flutter | `make flutter-test` | **49 testes** — green |
| Playwright | `make e2e` | **251 testes** — green |
| Tokens | `make tokens-check` | em sincronia, sem cores à mão |

## Acceptance criteria

| AC | Critério | Evidência | Estado |
|----|----------|-----------|--------|
| AC1 | O site e o painel **servem** as fontes | `document.fonts.check('16px Inter')` → `True`; `document.fonts.check('600 16px Poppins')` → `True`; e o pedido `GET /templates/default/fonts/Inter-Regular.woff2` → **200** (`font/woff2`, 23 640 B) | ✅ |
| AC2 | O painel e a app usam a **mesma** tipografia | O CSS: `--font-base: Inter, system-ui, …` (sem aspas); o Dart: `fontFamily: LkFonts.base` (`"Inter"`) + `fontFamilyFallback`. A app pede `Inter-Regular.ttf` ao servidor ✓ | ✅ |
| AC3 | O logótipo aparece no site, no painel e na app | O cabeçalho do painel mostra a chávena com o wordmark ✓; a gaveta da aplicação também ✓ | ✅ |
| AC4 | O logótipo é **legível no modo escuro** | A variante escura: o wordmark preto passa a branco, e o interior da chávena a transparente — assim funciona em qualquer fundo escuro | ✅ |
| AC5 | O token da marca é o do logótipo | `--color-primary: #fbb202` (o amarelo do logótipo), em vez de `#f8b400` (o do tema antigo) | ✅ |
| AC6 | Nenhuma cor à mão | `make tokens-check` — verde ✓ | ✅ |
| AC7 | Todos os portões verdes | tabela acima | ✅ |

## O defeito que os testes não apanharam (e o ecrã sim)

O token era:

```css
--font-base: "Inter, system-ui, -apple-system, sans-serif";
```

Com as aspas, é **um** nome de família. O navegador procurava uma
família chamada `Inter, system-ui, -apple-system, sans-serif` — e
não a encontrava. O painel estava a usar a **serif** por omissão, e
a aplicação a **Roboto** (porque o `fontFamily` do Flutter também
não é uma cadeia).

Nenhum teste falhou: o CSS era válido, e o Dart também. Só o
**ecrã** mostrava. E é a ficha que o operador deu: «incluso no tipo
de letra».

**A lição:** um teste que verifica a *existência* da regra não
verifica a sua *eficácia*. O teste que apanha isto é o que pergunta
«a fonte carregou?» — `document.fonts.check('16px Inter')`.

## As fontes — de onde vem

```
flutter/xkhm_app/assets/fonts/Inter-Regular.ttf   (66 592 B)
  │
  └─ tools/build_web_fonts.py ──► public/templates/default/fonts/Inter-Regular.woff2
                                   (23 640 B — 35%)
```

Uma fonte, duas saídas. E as faces são **geradas** a partir dos
tokens (`make tokens`), com o `font-display: swap` — o texto aparece
imediatamente, e a troca de fonte quando a chega.

## O logótipo

| Variante | Ficheiro | Onde |
|----------|----------|------|
| Original | `public/templates/default/images/linuxkafe.svg` | superfícies claras |
| Escura | `public/templates/default/images/linuxkafe-escuro.svg` | os cabeçalhos (que são escuros) |
| Original (PNG) | `flutter/xkhm_app/assets/images/linuxkafe-claro.png` | a barra da aplicação, no modo claro |
| Escura (PNG) | `flutter/xkhm_app/assets/images/linuxkafe.png` | a barra da aplicação, no modo escuro |

A aplicação escolhe a variante **pelo modo** (`Theme.of(context).brightness`),
e a gaveta usa a escura — porque o cabeçalho da gaveta é o da
aplicação, que é escuro nos dois modos.
