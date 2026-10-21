---
id: SD-META-014
title: "Tokens de design: um gerador torna a deriva detectável"
tier: meta
epistemic_state: verified
confidence: 0.9
date: 2026-10-21
source: T020
tags: [design-tokens, css, flutter, single-source-of-truth, brand]
---

# Tokens de design: um gerador torna a deriva detectável

## O problema

O operador pediu a mesma paleta para o CSS que já existia **e** para a nova
aplicação Flutter. Duas listas de tokens escritas à mão divergem no primeiro
edito — é uma questão de tempo, não de sorte.

E o Flutter não consome CSS: não há `--color-primary` no Dart. A tradução
tem de ser manual, o que torna a deriva ainda mais provável.

## A solução: um gerador, não dois ficheiros

```
assets/design_tokens.json      ← a única fonte de verdade
        │
        ├── tools/sync_tokens.py ──► public/templates/default/css/tokens.css
        │                          └──► flutter/xkhm_app/lib/theme/tokens.dart
```

O `make tokens` regenera os dois. O `make tokens-check` regenera para um
directório temporário e **compara** — a deriva passa a ser uma falha de
build, não uma diferença subtil que ninguém nota.

## O guarda extra

Um gerador sozinho não impede alguém de escrever `#f8b400` directamente num
widget. Por isso existe também:

```
tools/check_no_hardcoded_colors.py
```

que falha se encontrar um literal hexadecimal em qualquer ficheiro Dart (fora
do `tokens.dart` gerado). Uma linha de shell no `Makefile` transforma isso
num gate:

```make
tokens-check:
	@python3 tools/sync_tokens.py --check
	@python3 tools/check_no_hardcoded_colors.py
```

## O truque que o `sync_tokens.py` usa

As chaves do JSON são **os próprios nomes das custom properties do CSS**
(`color-primary`, `color-text-light`). Assim o mapeamento para Dart é
mecânico e não há uma tabela de tradução para manter:

```python
def dart_name(name: str) -> str:
    """`color-text-light` -> `textLight` (the `color-` prefix is implied)."""
```

As cores semânticas (sucesso / aviso / perigo / informação) foram para o
mesmo ficheiro pelo mesmo motivo: antes estavam hard-coded em três sítios
diferentes.

## A lição

**O objectivo não é "ter tokens". É "ter uma única definição da marca que os
dois clientes não podem divergir".** O gerador é o que transforma a segunda
em impossibilidade estrutural, e não numa convenção que se perde com o
tempo.

## Ver

- `assets/design_tokens.json`
- `tools/sync_tokens.py`
- `tools/check_no_hardcoded_colors.py`
