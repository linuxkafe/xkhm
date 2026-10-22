---
ticket: T049
phase: verify
status: done
created: 2026-10-22
verdict: pass
requires:
  - aes/tickets/T049-plan.md
---

# T049 — Verify: A interface de 2026, ao estilo do design system

## Gates

| Gate | Command | Result |
|------|---------|--------|
| PHPUnit | `make test` | **208 tests, 717 assertions** — green |
| PHPStan (nível 5) | `make lint` | **0 erros** |
| Flutter | `make flutter-test` | **50 testes** — green |
| Playwright | `make e2e` | **251 testes** — green |
| Tokens | `make tokens-check` | em sincronia, sem cores à mão |

## Acceptance criteria

| AC | Critério | Evidência | Estado |
|----|----------|-----------|--------|
| AC1 | Uma **única** família tipográfica | `--font-base` e `--font-heading` → o segundo desapareceu; o Dart: `LkFonts.base` (Inter) e `LkTheme.fontHeading` → `LkFonts.base` | ✅ |
| AC2 | A escala é a do design system | `--type-h1-size: 24px`, `--type-h1-weight: 600`, `--type-body-size: 14px`, `--type-body-line-height: 1.5`; e `LkTypeScale.h1Size` = 24, `LkTypeScale.bodySize` = 14 | ✅ |
| AC3 | Os títulos têm **contraste suficiente** | O `h1, h2, h3, h4` passam de `var(--color-primary)` (1.9:1) para `var(--color-text)` (16:1); o amarelo fica como acento | ✅ |
| AC4 | Os componentes seguem o sistema | as tabelas com divisores subtis (sem grelha); os botões sem a inversão de cor no *hover*; os cartões com borda (sem sombra); as divisões do design system's 4px base | ✅ |
| AC5 | O logótipo vai **sobre fundo branco** | a placa `var(--color-logo-plate)` (branco) com o padding; a variante original (o wordmark preto) fica fiel | ✅ |
| AC6 | O esquema de cores **não muda** | a paleta é a mesma; só o token da marca passou a ser o do logótipo (`#fbb202`) | ✅ |
| AC7 | Todos os portões verdes | tabela acima | ✅ |

## A modernização, verificada

A página do painel (o `products.php`):

- O título «Produtos e serviços» é agora em **Inter** 20 px, 600, com a cor do texto ✓ (antes: Poppins 500, 22 px, amarelo);
- A tabela tem os **divisores subtis** (a linha de baixo, sem grelha) e um cabeçalho em caixa-alta, com letra-espaçada ✓;
- A sombra do painel desapareceu (borda de 1 px) ✓;
- O logótipo vai sobre a placa branca, com o wordmark preto intacto ✓.

A aplicação: o `textTheme` é a escala do design system (24/20/14/12), e a
família é a Inter em todo o lado.

## O defeito que o ecrã apanhou

O `--font-base: "Inter, system-ui, -apple-system, sans-serif"` (com as
aspas) era **um** nome de família. O navegador procurava uma família
chamada `Inter, system-ui, -apple-system, sans-serif` e não a
encontrava. O painel estava com a **serif** por omissão, e a aplicação
com a **Roboto**.

Nenhum teste falhou: o CSS era válido, e o Dart também. Só o ecrã
mostrava. E é a ficha que o operador deu: «o tipo de letra continua a
não ser o moderno».

**A lição:** um teste que verifica a *existência* da regra não verifica
a sua *eficácia*. O teste que apanha isto é o que pergunta «a fonte
carregou?» — `document.fonts.check('16px Inter')`.

E a mesma confusão estava no **Dart**: o `fontFamily` do Flutter não é
uma cadeia. A app embute a Inter desde o início e nunca a usou.

## O que o design system trouxe (e o que não trouxe)

| trouxe | não trouxe |
|--------|------------|
| A escala tipográfica (24/20/14/12) | A paleta — o esquema de cores é o do LINUXKAFE |
| A forma dos componentes (bordas, sem sombras, divisores subtis) | As referências à UP — nenhum nome, nenhuma URL |
| A única família (a Inter) | O modo escuro — o painel é claro, por omissão |
| A placa branca do logótipo | |
| O contraste suficiente dos títulos | |
