---
ticket: T049
phase: plan
status: done
created: 2026-10-22
tier: heavy
verdict: pass
requires:
  - aes/tickets/T049.md
produces:
  - aes/tickets/T049-plan.md
blocked_by: ''
---

# T049 — Plan: A interface de 2026, ao estilo do design system

## Two-Agent Analysis (heavy tier)

### Critic Phase

```
## Como se fosse uma criança
A loja tinha as cores certas mas a letra errada e a mesa torta.
Agora a mesa e' direita e a letra e' a da marca. O logotipo e' a
chaven com o nome, sobre uma folha branca.

## Como se fosse um especialista

[FAILURE MODE 1] — O amarelo do logótipo não é o amarelo do token.

Mecanismo: o token dizia `#f8b400` (da folha de estilo do tema
WordPress) e o logótipo dizia `#fbb202`. Dois amarelos para a mesma
marca — e o operador pediu o logótipo.

Mitigation: o token passa a ser o **do logótipo** (que é a versão
mais recente da marca), e o teste da paleta é actualizado.

[FAILURE MODE 2] — O logótipo sobre fundo escuro **desaparece**.

Mecanismo: o wordmark «LINUX» é preto (`#000000`), e o cabeçalho
é `#2d2d2d`. O wordmark fica quase invisível.

A primeira ideia (inverter) troca a marca: o «LINUX» preto deixa de
ser o «LINUX» preto. A segunda (inverter só o preto) deixa o
interior da chávena **branco** — uma chávena branca com o interior
branco, ou seja, sem chávena.

Mitigation: o logótipo vai sobre uma **placa branca**. A marca fica
fiel, e o fundo branco é o fundo que o logótipo tem no papel.

[FAILURE MODE 3] — Os títulos são amarelos sobre branco.

Mecanismo: `h1, h2, h3, h4` eram `var(--color-primary)`. O contraste
do `#fbb202` sobre `#ffffff` é de **1.9:1** — e o WCGA exige
4.5:1 (3:1 para texto grande). Um título amarelo sobre branco não
se lê.

Mitigation: os títulos passam à **cor do texto**; o amarelo fica como
acento (o item activo, o botão primário, o anel de foco).

[FAILURE MODE 4] — A família tipográfica é **duas**, e a segunda é
de 2016.

Mecanismo: a Inter para o corpo, a Poppins para os títulos. A
Poppins é uma face geométrica de 2016 — é o «tipo de letra de
2014» que o operador reconheceu. E o design system nomeia **uma**
família.

Mitigation: **uma só família** (a Inter), com a escala e os pesos
do design system. A Poppins sai dos tokens e do código.

[FAILURE MODE 5] — A escala é a do Material, e não a do sistema.

Mecanismo: o `textTheme` do Flutter era o padrão do Material — um
display de 57 px e uma etiqueta de 11 px. A escala do design system
é 32/24/20/14/12.

Mitigation: a escala é definida nos tokens e gerada para os dois
lados.

[FAILURE MODE 6] — Os componentes têm sombras onde o sistema diz
"bordas".

Mecanismo: `.whmcscontainer { box-shadow: var(--shadow-card) }`, os
botões com `transition: all`, os campos com sombra, as tabelas com
uma grelha de 1 px. O design system diz «no heavy shadows — use
borders instead» e «subtle borders, no heavy shadows».

Mitigation: as sombras saem, as tabelas passam a ter divisores
subtis, os botões têm transição só da cor.

[ASSUMPTION]
Se falso: se o design system's **form** não for o que o operador quer.

O operador pediu «mais ao estilo do design system, mas sem referências
da UP, e mantendo o esquema de cores». O estilo é a **forma**
(espaçamento, bordas, escala, componentes), e as cores são as
LINUXKAFE. Ambos são respeitados.

[ALTERNATIVE FRAMING]
Em vez de reescrever o CSS, adoptar um framework (Tailwind, Bootstrap
5).

Rejeitado: o CSS já existe, e é o que o painel usa. Reescrever o
framework é mais trabalho do que reescrever os componentes. E o
design system diz «use the color palette above, not arbitrary
colors» — um framework traz a sua paleta.
```

## A solução

1. `assets/design_tokens.json` — a escala tipográfica, uma só
   família, e a sombra a `none`.
2. `tools/sync_tokens.py` — gera as `@font-face` e a escala, para
   o CSS e para o Dart.
3. `public/templates/default/css/whmcs.css` — a escala, os títulos na
   cor do texto, as tabelas com divisores subtis, os botões sem
   inversão, os cartões com borda, o logótipo sobre a placa.
4. `flutter/xkhm_app/lib/theme/lk_theme.dart` — a escala, uma
   família, a placa do logótipo.
5. `public/templates/default/images/linuxkafe{-escuro}.svg` — as
   duas variantes; a app usa a **original** sobre a placa.

## O que fica de fora

- **Um eixo tipográfico novo** (a escala de tamanhos): a actual está
  coerente, e mexer nela sem um desenho é *scope creep*.
- **As fontes variables** (o Inter variable): o `.ttf` embebido é
  estático, e convertê-lo daria um ganho mínimo.
- **A migração para o Flutter** (o painel em Flutter): é um projecto
  próprio, e o painel actual funciona.
- **O modo escuro do painel** — o painel é claro por omissão, e o
  modo escuro é uma decisão de produto, não de estilo.
