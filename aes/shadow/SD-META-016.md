---
id: SD-META-016
title: "A paleta é uma função do modo, não um ficheiro"
tier: meta
epistemic_state: verified
confidence: 0.9
date: 2026-10-21
source: T023
tags: [design-tokens, dark-mode, css, flutter, single-source-of-truth]
---

# A paleta é uma função do modo, não um ficheiro

## O erro que a primeira versão cometeu

Quando o dark mode foi pedido, a primeira tentativa foi添加 um ficheiro
`tokens-dark.css` e uma classe `LkColorsDark`. Duas listas de novo — o
exactamente o problema que SD-META-014 já tinha resolvido.

O que apareceu logo a seguir:
- o `whmcs.css` tinha o **seu próprio** `:root` (cópia da paleta clara), que
  vinha **depois** do `tokens.css` no cascade — logo, o bloco
  `@media (prefers-color-scheme: dark)` era **sobrescrito** e o modo
  escuro não funcionava no site;
- o `bootstrap.css` — um resto do WHMCS original com a paleta azul antiga
  (`#2a3b5c`) — duplicava a grelha e trazia uma segunda paleta;
- a semântica Flutter não resolvia o modo: `LkColors.bg` era uma constante
  de compilação, e uma constante não se repinta quando o sistema muda.

## A forma que funciona

```
assets/design_tokens.json
  ├── colors      (claro)
  ├── darkColors  (escuro — as MESMAS chaves)
  ├── semantic    / darkSemantic
  └── mobile      (alvos de toque, breakpoints)
        │
        ├── tools/sync_tokens.py ──► tokens.css  ( :root + @media (prefers-color-scheme: dark) )
        │                       └──► tokens.dart ( LkColors.light / LkColors.dark )
        └── tools/check_no_hardcoded_colors.py  ( Dart **e** CSS )
```

Três decisões carregam o peso:

### 1. O validador recusa paletas com chaves diferentes

```python
if light != dark:
    sys.exit(f"error: '{dark_key}' must define the same keys as '{light_key}'")
```

Sem isto, um token esquecido no modo escuro passaria silenciosamente a
mostrar a cor do modo claro — exactamente o tipo de bug que o dark mode
introduz.

### 2. A cor vive no **tema**, não no widget

```dart
// ANTES: uma constante — não se repinta quando o modo muda
color: LkColors.bgCard,

// DEPOIS: o tema — o Flutter reconstrói-o quando o modo muda
color: theme.cardTheme.color,
```

Só a paleta de **marca** (o amarelo primário) fica constante. Todo o resto
passa pelo `ThemeData`, que é reconstruído em `MediaQuery.platformBrightnessOf`.

### 3. O guarda de cores cobre os **dois** clientes

O guarda original só olhava para o Dart, e o `Colors.white` passava por
dentro. Agora:

- Dart: `0xAARRGGBB`, `#RRGGBB`, `Colors.*`, `Color.fromARGB(...)`
- CSS: `#hex`, `rgb()`, `rgba()`

E apanha os literais **fora** dos ficheiros gerados.

## O erro do `@media` que quase não se vê

A regra do CSS que falhava era esta:

```css
:root { --color-bg: #ffffff; }               /* whmcs.css, mais tarde */
@media (prefers-color-scheme: dark) { :root { --color-bg: #141414; } }  /* tokens.css, antes */
```

A ordem no cascade decide. O `whmcs.css` vinha depois, com a **mesma**
especificidade, logo vencia — e o modo escuro nunca se aplicava.

A lição não é "não repitas o `:root`". É: **quando a paleta é gerada, o
ficheiro gerado tem de ser a única fonte da Custom Property.** As folhas
manuais consomem tokens; nunca os definem.

## A lição

**Suportar N modos é uma mudança na *forma* da paleta, não na quantidade de
ficheiros.** A paleta passa a ser uma função `modo → cores`, e o gerador
passa a produzir uma representação por cliente e por modo. Com isso, o
número de clientes deixou de ser o número de ficheiros.

## Ver

- `assets/design_tokens.json`
- `tools/sync_tokens.py` (validador de chaves)
- `tools/check_no_hardcoded_colors.py` (Dart + CSS)
- `flutter/xkhm_app/lib/theme/lk_theme.dart` (um construtor, duas paletas)
- `public/templates/default/css/tokens.css` (gerado)
