---
ticket: T036
phase: verify
status: done
created: 2026-10-21
verdict: pass
requires:
  - aes/tickets/T036-plan.md
---

# T036 — Verify: A interface mobile, modernizada

## Gates

| Gate | Command | Result |
|------|---------|--------|
| PHPUnit | `make test` | **128 tests** — green |
| PHPStan (nível 5) | `make lint` | **0 erros** |
| Flutter | `make flutter-test` | **49 testes** — green (45 + 4 novos) |
| Playwright | `make e2e` | **202 testes** — green (195 + 7 novos) |
| Tokens | `make tokens-check` | em sincronia, sem cores literais |

## Antes e depois

| | Antes | Depois |
|---|-------|--------|
| A saudação | um cartão escuro, ~300 px, só para dizer o nome | um **herói** com o nome, o email e as duas figuras |
| As figuras | empilhadas (220 px fixos × 2 num ecrã de 390) | **lado a lado**, com a largura de que precisam |
| A primeira secção | começava a ~435 px | começa a ~270 px |
| As notícias | o corpo inteiro, 15 linhas | **três linhas** e um «ler mais» |
| O código do carregamento | `LkHtml` (um `Text.rich`) | `LkExpandableText` — três linhas, com «ler mais» |
| O gesto de recarregar | nenhum | **pull-to-refresh** em cinco ecrãs |

## Acceptance criteria

| AC | Critério | Evidência | Estado |
|----|----------|-----------|--------|
| AC1 | As figuras ficam lado a lado num telefone de 390 px | `the figures share the row on a phone` (widget, com a geometria real) e `test_the_figures_are_side_by_side` (Playwright, 390 px) | ✅ |
| AC2 | A saudação é um herói compacto | `test_the_greeting_is_compact` — a primeira secção começa acima de um terço do ecrã | ✅ |
| AC3 | As notícias resumem-se, com «ler mais» | `LkExpandableText only offers "read more" when it clips` (widget) e `test_the_articles_are_summarised` | ✅ |
| AC4 | A barra de navegação tem indicador | **já tinha** — a barra é a `NavigationBar` do Material 3; `test_the_bottom_bar_has_the_modern_indicator` | ✅ |
| AC5 | Os ecrãs de dados têm pull-to-refresh | `LkRefreshable gives the list the pull-to-refresh gesture` (widget, com um `fling` real) | ✅ |
| AC6 | Sem scroll horizontal, alvos ≥44 px, dark mode intacto | `test_there_is_no_horizontal_overflow`; o `ConstrainedBox(minHeight: 44)` do botão; `test_the_dark_mode_still_works` | ✅ |
| AC7 | Todos os portões verdes | tabela acima | ✅ |

## A decisão que vale a pena registar

O botão «ler mais» **só aparece quando o texto é mesmo cortado** — e isso
é **medido**, não adivinhado:

```dart
final painter = TextPainter(
  text: TextSpan(text: widget.text, style: style),
  maxLines: widget.maxLines,
  textDirection: TextDirection.ltr,
)..layout(maxWidth: constraints.maxWidth);

final clipped = painter.didExceedMaxLines;
```

Um «ler mais» que abre nada é pior do que não ter botão.

E o resumo é **visual**: o nó de semântica do Flutter carrega o texto
**todo**, não só a parte visível. Quem usa um leitor de ecrã não perde o
fim do anúncio — o corte é uma cortesia visual, e o botão existe para
quem lê com os olhos. O teste
`test_the_full_text_is_announced` garante isso.

## O que **não** mudou

- O `LkStatTile` — a alteração é a **largura** com que o ecrã o coloca
  (`Expanded` em vez de `SizedBox(width: 220)`). O widget partilhado
  fica como estava, para não mexer nos ecrãs onde o layout já estava
  bom.
- O painel (que é PHP) — não é a mesma coisa, e mexer nele aqui seria
  escopar a esfera.
- O modo escuro — os tokens tratam disso, e o verificador de cores
  continua a falhar o build se aparecer um literal.

## Defeitos encontrados durante a verificação

| # | Defeito | Causa | Correcção |
|---|---------|-------|-----------|
| 1 | Um teste existente falhou: `assert "linuxkafe" in body` | a asserção verificava a **marca no fim do anúncio** («Obrigado LINUXKAFE»), e não a identidade do cliente — o resumo passou a(clipá-la) | a asserção passou a verificar a marca **na barra** e a identidade pelo email do cliente |
| 2 | O texto do anúncio desaparecia da leitura do teste | o `inner_text` do host só chega aos filhos **directos**; o Flutter funde textos adjacentes num nó mais fundo | o teste passa a ler os rótulos de **toda** a árvore |
| 3 | O login falhava de vez em quando | `fill()` **não** funciona no Flutter — a primeira tecla perde-se | o helper `_fill` (focar, limpar, escrever, verificar) |
| 4 | A semântica materializava-se de forma intermitente | é assíncrona, e o `flt-semantics` não existe — a árvore vive em `flt-semantics-host` | o helper espera pelo **conteúdo** da árvore, e repete |
| 5 | O `dart format` reformatou código não relacionado | o projecto não está formatado com o formatador | as mudanças foram reaplicadas à mão, e o `diff` ficou só com o que é meu |

O primeiro é o mais instructive: **a asserção estava a verificar a coisa
errada**, e só falhou quando outra coisa mudou. Um teste que passa
pelo motivo errado é pior do que um teste que não existe — porque
protege contra o erro errado.
