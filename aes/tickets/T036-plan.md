---
ticket: T036
phase: plan
status: done
created: 2026-10-21
tier: heavy
verdict: pass
requires:
  - aes/tickets/T036.md
produces:
  - aes/tickets/T036-plan.md
blocked_by: ''
---

# T036 — Plan: A interface mobile, modernizada

## Two-Agent Analysis (heavy tier)

### Critic Phase

```
## Como se fosse uma criança
A casa tem um painel onde eu vejo os meusDinheiros, e cada um esta
numa caixa enorme. Ha duas caixas, e so cabe uma de cada vez. E o
quadro das noticias e tao grande que tenho de rolar muito para chegar
ao fim.

## Como se fosse um especialista

[FAILURE MODE 1] — "Modernizar" sem medida é gutar.

Mecanismo: um ticket de interface sem critério verificável acaba em
discussão de gosto, e o que fica é o mesmo ecrã com mais sombras.

Mitigation: cada AC é **verificável por um teste**: as figuras lado a
lado aos 390 px, o herói com menos de N píxeis de altura, o resumo com
três linhas, o indicador na barra, o pull-to-refresh a funcionar. E
há uma **captura** antes e depois — a comparação é visual, mas é
baseada em factos.

[FAILURE MODE 2] — Mexer no `LkStatTile` afectaria **todos** os ecrãs
que o usam.

Mecanismo: o widget é partilhado. Uma alteração de tamanho propaga-se
a telas onde o layout já estava bom.

Mitigation: o widget fica **igual**; o que muda é a **largura** com que
o ecrã de início o coloca — `Expanded` em vez de `SizedBox(width: 220)`.
O layout passa a ser **fluido**, e o widget continua a ser o que é.

[FAILURE MODE 3] — O «ler mais» pode esconder informação que o
cliente precisa.

Mecanismo: o corpo de um anúncio truncado pode ser a parte importante.

Mitigation: o corte é de **três linhas**, o «ler mais» expande, e o
texto continua a ser seleccionável e legível por um leitor de ecrã. O
conteúdo não se perde — só aparece depois de um toque.

[FAILURE MODE 4] — O pull-to-refresh num ecrã que não carrega nada
pode ser um gesto vazio.

Mecanismo: um `RefreshIndicator` sobre um ecrã sem dados é um gesto que
não faz nada.

Mitigation: só nos ecrãs que **carregam** dados (início, faturas,
tickets, domínios, serviços). E o gesto recarrega mesmo.

[FAILURE MODE 5] — Modernizar e quebrar o dark mode.

Mecanismo: as coresHard-coded num herói novo.

Mitigation: o herói usa os **tokens** (o gradiente vem de `--color-
primary`), e o verificador de cores continua a falhar o build se
aparecer um literal. E o teste de dark mode continua a correr.
```

### Implementor Phase

```
## Como se fosse uma criança
As duas caixas ficam lado a lado e sao pequenas. O quadro de cima e
simpples, com o nome. As noticias mostram so o comeco, e ha um botao
que mostra o resto.

## Como se fosse um especialista

(D1) O herói.

```dart
Container(
  decoration: BoxDecoration(
    gradient: LinearGradient(
      colors: [theme.colorScheme.primary, theme.colorScheme.primary.withValues(alpha: 0.75)],
      begin: Alignment.topLeft, end: Alignment.bottomRight,
    ),
    borderRadius: BorderRadius.circular(20),
  ),
  ...
)
```

O gradiente sai dos **tokens** — o mesmo `--color-primary` do resto.

(D2) As figuras lado a lado: `Row` com dois `Expanded`, e o `LkStatTile`
num **contentor** mais compacto (o valor grande, o rótulo pequeno).

(D3) O resumo: `Text` com `maxLines: 3` e `overflow: TextOverflow.ellipsis`,
e um `TextButton` que alterna o estado.

(D4) O pull-to-refresh: `RefreshIndicator` em volta do `ListView`.

(D5) A barra: `NavigationBar` do Material 3, com o indicador.
```

## Addressing FAILURE MODE 1 (modernizar sem medida)

Mitigation: as ACs são verificáveis, e a verificação inclui capturas
antes e depois.

## Addressing FAILURE MODE 2 (o widget partilhado)

Mitigation: o `LkStatTile` não muda; muda a **largura** com que o ecrã
o coloca.

## Addressing FAILURE MODE 3 (conteúdo escondido)

Mitigation: três linhas, e o «ler mais» expande. Nada se perde.

## Addressing FAILURE MODE 4 (gesto vazio)

Mitigation: só onde há dados.

## Addressing FAILURE MODE 5 (o dark mode)

Mitigation: os tokens, e o verificador de cores.

## [SOLUTION PROPOSAL]

Stage 1 — o herói e as figuras.
Stage 2 — o resumo com «ler mais».
Stage 3 — o pull-to-refresh.
Stage 4 — a barra de navegação.
Stage 5 — as capturas e os testes.

## Technical Approach

**Chosen:** o layout passa a ser **fluido** (o conteúdo decide a
largura, não um número fixo), e o ecrã de início passa a ter a
hierarquia de uma aplicação moderna: identidade, números, e depois o
conteúdo.

**What will NOT be implemented:** transições animadas; um redesenho
completo; mexer no painel (que é PHP).

### Affected files

| File | Operation | Description |
|------|-----------|-------------|
| `flutter/xkhm_app/lib/screens/home_screen.dart` | modify | O herói e as figuras |
| `flutter/xkhm_app/lib/widgets/lk_widgets.dart` | modify | O cartão compacto, o herói, o resumo |
| `flutter/xkhm_app/lib/screens/home_shell.dart` | modify | A barra e o pull-to-refresh |
| `flutter/xkhm_app/lib/screens/invoices_screen.dart` | modify | O pull-to-refresh |
| `flutter/xkhm_app/lib/screens/tickets_screen.dart` | modify | O pull-to-refresh |
| `flutter/xkhm_app/lib/screens/domains_screen.dart` | modify | O pull-to-refresh |
| `flutter/xkhm_app/lib/screens/services_screen.dart` | modify | O pull-to-refresh |
| `tests/Playwright/test_mobile_ui.py` | create | A verificação |

## Testing Criteria

- [ ] As figuras estão lado a lado aos 390 px
- [ ] O herói é mais baixo que o cartão antigo
- [ ] O resumo corta a três linhas e expande
- [ ] O pull-to-refresh recarrega
- [ ] A barra tem indicador
- [ ] Sem scroll horizontal, alvos ≥44 px, dark mode intacto

## Estimation

- Complexity: medium (4-8h)
- Risk: low — é a camada visual, e o verificador de cores protege
- Blocking dependencies: none
