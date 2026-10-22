---
id: SD-UX-001
title: "«Não está disponível» é um sintoma de layout, não de funcionalidade"
tier: ux
epistemic_state: verified
confidence: 0.95
date: 2026-10-22
source: T044
tags: [flutter, navigation, ux, layout, symptom]
---

# «Não está disponível» é um sintoma de layout, não de funcionalidade

## O que aconteceu

O operador escreveu:

> «as funcionalidades como a pesquisa de domínio ou status do
> servidor também não estão disponíveis»

E as duas **existiam**: o ecrã do oidor de domínios estava no menu, e
o do estado do servidor também. O teste de Playwright encontrava os
dois e clicava neles sem problema.

O que faltava era o **caminho**. A `NavigationRail` do Flutter **não
rola**:

```dart
NavigationRail(
  destinations: [ /* quinze destinos */ ],
  // scrollable: false — o omisso
)
```

Com quinze destinos e um ecrã de 900 px de altura, os últimos ficam
abaixo da dobra. E no ecrã **largo** não há gaveta — a gaveta é do
ecrã estreito. Portanto, no computador, «Procurar Domínio» e «Estado
da Rede» eram **inalcançáveis**: não era que a função não existisse,
era que não se chegava lá.

O teste que o apanhou foi o mais óbvio de todos:

```
element is visible, enabled and stable
  - element is outside of the viewport
```

## A regra

> **«A função existe?» é a pergunta errada. A pergunta é «o utilizador
> consegue chegar lá?» — e a resposta depende do ecrã, não do código.**

A mesma aplicação é completa num ecrã e incompleta noutro. Um teste
que abre a funcionalidade **num ecrã largo, clicking na última
entrada do menu**, é o que prova a diferença.

E a variante mais cruel do defeito: o menu **parece** completo. Não
há aviso, não há erro, não há scroll. A funcionalidade está lá, e
inacessível.

## A regra derivada

> **Toda a navegação que cresce precisa de uma prova de que cresce.**

Um menu com quatro destinos não precisa de teste. Com quinze, precisa
— e o teste é «o último destino é alcançável no ecrã mais baixo».

## Ver

- `flutter/xkhm_app/lib/screens/home_shell.dart` — `scrollable: true`
- `tests/Playwright/test_flutter_app.py` — a lista de destinos, e o
  clique no último
