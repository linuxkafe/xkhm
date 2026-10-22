---
id: SD-CI-003
title: "Compatibilidade mobile é funcional, não visual"
tier: ci
epistemic_state: verified
confidence: 0.9
date: 2026-10-21
source: T023
tags: [mobile, responsive, testing, playwright, accessibility]
---

# Compatibilidade mobile é funcional, não visual

## A distinção

"Compatibilidade mobile" não é "o layout parece bem a 390 px". As falhas
reais são estruturais:

| Falha | Como se manifesta | Como se prova |
|-------|-------------------|---------------|
| Scroll horizontal | a página é mais larga que o ecrã | `scrollWidth - clientWidth <= 1` |
| Alvo de toque pequeno | o dedo falha em acertar | `getBoundingClientRect().height >= 44` |
| Secção inalcançável | a navegação não mostra tudo | a acção está no DOM e é clicável |
| Conteúdo sob o entalhe | a barra de sistema tapa o topo | `env(safe-area-inset-*)` aplicado |
| Teclado virtual tapa o campo | o campo foca-se mas não se vê | `MediaQuery.viewInsetsOf(context)` |

Nenhuma delas se apanha com uma captura de ecrã.

## O caso real que encontrei

O `NavigationBar` do Flutter cabiam 4 destinos. Havia **7** secções. Três
eram literalmente inalcançáveis num telemóvel — e nenhuma captura de ecrã
ia mostrar isso, porque o ecrã parecia correcto.

A correcção foi uma `NavigationDrawer` com todas as secções, e o teste
passou a afirmar que as **7** estão lá:

```python
for index, label in enumerate(ALL_DESTINATIONS):
    item = app_page.locator(f'[flt-semantics-identifier="drawer-destination-{index}"]')
    assert item.count() == 1, f"{label} is missing from the drawer"
```

## A técnica da tabela responsiva sem markup

Uma tabela que não cabe não deve alargar a página. A solução sem tocar em
uma linha de HTML:

```css
@media (max-width: 768px) {
    .table {
        display: block;
        width: 100%;
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
        white-space: nowrap;
    }
}
```

`display: block` numa tabela transforma-a num contentor de scroll. O
`border-collapse` continua a ser `collapse`, as celas continuam alinhadas, e
a página não cresce. Medido: `scrollWidth 415 > clientWidth 296` dentro da
tabela, mas `document.scrollWidth - document.clientWidth == 0`.

## O detalhe do alvo de toque

Nem todos os elementos interactivos precisam de 44 px. Um link dentro de um
parágrafo é texto, não um botão — ampliar um parágrafo quebraria o fluxo. A
excepção tem de ser explícita no teste, senão o teste passa a falhar por
razões que não são o bug:

```javascript
// Inline links inside a paragraph are exempt.
if (el.tagName === 'A' && el.closest('p, li, td, .text-center')) continue;
```

Já um link dentro de um `.panel-heading` **é** um alvo de toque — e o teste
encontrou-o a 16 px. O CSS ganhou `min-height: var(--touch-target-min)`.

## A regra de ouro

**Um requisito de "compatibilidade" só está cumprido quando existe uma
asserção automática que falha quando deixa de estar.** Uma captura de ecrã
nunca falha.

## Ver

- `tests/Playwright/test_dark_mode.py` — 66 asserções (scroll, alvos,
  safe areas, contraste, os dois modos)
- `tests/Playwright/test_flutter_app.py` — gaveta, navegação, overflow
- `public/templates/default/css/whmcs.css` — as media queries
