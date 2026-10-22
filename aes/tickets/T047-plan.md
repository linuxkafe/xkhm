---
ticket: T047
phase: plan
status: done
created: 2026-10-22
tier: heavy
verdict: pass
requires:
  - aes/tickets/T047.md
produces:
  - aes/tickets/T047-plan.md
blocked_by: ''
---

# T047 — Plan: A interface de 2026 — o tipo de letra e o logótipo

## Two-Agent Analysis (heavy tier)

### Critic Phase

```
## Como se fosse uma criança
A marca era um nome escrito a mao. Agora e' a imagem verdadeira: uma
chaven com o nome ao lado. E as letras sao as de verdade — as do
aplicativo. Antes, o painel e a app tinham letras diferentes, e o
painel nao tinha as suas: estava a usar as do sistema.

## Como se fosse um especialista

[FAILURE MODE 1] — As fontes estavam **nomeadas**, não servidas.

Mecanismo: os tokens diziam `--font-base: "Inter, system-ui, …"`.
Com as aspas, isso é **um** nome de família — uma família chamada
`Inter, system-ui, -apple-system, sans-serif`, que não existe. O
navegador caía no `serif` por omissão. E o mesmo valor, no Dart,
era uma única família (o `fontFamily` do Flutter não é uma cadeia) —
pelo que a app também nunca usou a Inter, e sim a Roboto.

Mitigation: o token passa a ter a **família** e a **cadeia de
recurso** separadas, e cada lado as converte à sua língua: o CSS
com a lista, o Dart com `fontFamily` + `fontFamilyFallback`.

[FAILURE MODE 2] — As fontes vêm de um CDN.

Mecanismo: a Content-Security-Policy é estrita (`font-src 'self'`), e
um pedido ao Google Fonts seria um dado que sai da máquina — e
bloqueado, portanto.

Mitigation: as fontes são **auto-hospedadas**, e derivadas dos
ficheiros que a aplicação **já embute** (`tools/build_web_fonts.py`
converte os `.ttf` em `.woff2`). Uma fonte, duas saídas — como os
tokens de design.

[FAILURE MODE 3] — O logótipo tem o wordmark **preto**, e o
cabeçalho é escuro.

Mecanismo: o wordmark «LINUX» é preto (`#000000`), e o cabeçalho é
`#2d2d2d`. O logótipo original, no cabeçalho, é quase invisível.

Mitigation: uma **variante escura** — o preto passa a branco, e as
partes claras (o interior da chávena) passam a **transparentes**.
Assim o logótipo funciona em qualquer fundo escuro, sem depender da
cor.

[FAILURE MODE 4] — A aplicação não tem SVG.

Mecanismo: o Flutter não lê SVG sem o pacote `flutter_svg` — uma
dependência nova, por um logótipo.

Mitigation: o logótipo é **rasterizado** (com o Playwright, que já é
uma dependência da suite) em PNG com transparência, e a variante
clara e a escura são as duas. Sem dependência nova.

[FAILURE MODE 5] — O amarelo da marca está em dois sítios, e em
sítios diferentes.

Mecanismo: o token diz `#f8b400` (da folha de estilo do tema) e o
logótipo diz `#fbb202` (2026/01). Dois amarelos para a mesma marca.

Mitigation: o token passa a ser o **do logótipo** — que é a versão
mais recente da marca — e o teste da paleta é actualizado.

[ASSUMPTION]
Se falso: se a marca de 2026 não for a do logótipo.

O logótipo foi publicado em 2026/01 no site da marca; o token
veio da folha de estilo do tema WordPress, que é mais antigo. O
logótipo é a fonte mais recente — e é o que o operador pediu para
aplicar.

[ALTERNATIVE FRAMING]
Em vez do logótipo, redesenhar a marca em texto com a Poppins.

Rejeitado: o operador pediu **o logótipo** (com a ligação), e a
chávena é o símbolo da marca.
```

## A solução

1. `tools/build_web_fonts.py` — os `.woff2` web, derivados dos `.ttf`
   da aplicação.
2. `assets/design_tokens.json` — os tokens da tipografia passam a ter
   a família e a cadeia; e o bloco `fonts` (as faces).
3. `tools/sync_tokens.py` — gera as `@font-face` e as duas
   traduções (CSS e Dart).
4. `public/templates/default/images/linuxkafe{,-escuro}.svg` — as
   duas variantes.
5. `flutter/xkhm_app/assets/images/linuxkafe{,-claro}.png` — as duas
   variantes, para a aplicação.
6. O cabeçalho do site, o do painel e a barra da aplicação usam o
   logótipo.

## O que fica de fora

- **Um eixo tipográfico novo** (a escala de tamanhos): a actual está
  coerente, e mexer nela sem um desenho é scope creep.
- **As fontes variables** (o Inter variable): o `.ttf` embebido é
  estático, e convertê-lo daria um ganho mínimo.
- **O `flutter_svg`**: rasterizado chega, e não acrescenta uma
  dependência.
