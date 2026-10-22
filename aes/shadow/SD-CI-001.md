---
id: SD-CI-001
title: "Flutter web + Playwright: o testing exige a árvore de semântica"
tier: ci
epistemic_state: verified
confidence: 0.95
date: 2026-10-21
source: T020
tags: [flutter, playwright, web, e2e, testing]
---

# Flutter web + Playwright: o teste exige a árvore de semântica

## O problema

O Flutter desenha a interface num `<canvas>`. O DOM não contém os widgets,
por isso `page.get_by_text('Faturas')` não encontra nada e
`page.inner_text('body')` devolve vazio.

## A solução

O Flutter web expõe a **árvore de semântica** (acessibilidade) como elementos
`flt-semantics` no DOM — mas **só a cria quando deteta uma tecnologia
assistiva**. O Playwright não a deteta.

A forma de a ativar é clicar no elemento oculto:

```html
<flt-semantics-placeholder role="button" aria-label="Enable accessibility">
```

O botão está posicionado fora do viewport, por isso um `click()` normal
falha com "Element is outside of the viewport". A solução é despachar o
evento:

```python
page.locator("flt-semantics-placeholder").first.dispatch_event("click")
page.wait_for_selector("flt-semantics", timeout=15_000)
```

Depois disso, `page.locator("flt-semantics-host").inner_text()` devolve todo
o texto visível da aplicação.

## Interação com campos de texto

O input semântico de um `TextField` nasce com `disabled`. O motor do Flutter
habilita-o quando o campo é **realmente focado**. O caminho que funciona:

```python
field = page.get_by_label("Email")
field.click()                      # foca o campo
page.keyboard.type("valor", delay=25)
```

`page.fill()` **não** funciona: preenche o input do DOM mas o
`TextEditingController` do Flutter não recebe o valor.

## A armadilha da corrida

Mesmo com `click()` + `type()`, o valor no DOM e o valor no
`TextEditingController` não são sincronizados instantaneamente. Se o botão
"Entrar" for clicado demasiado cedo, o servidor recebe a senha truncada e
responde "utilizador ou senha inválidos" — um falso negativo que parece um
bug de credenciais.

Mitigação: ler o valor com `expect(...).to_have_value(...)` (que faz retry) e
depois dar um pequeno settle antes de submeter.

## Identificadores estáveis

`Semantics(identifier: 'login-email')` produz o atributo
`flt-semantics-identifier` no DOM. Sem ele, só se pode selecionar campos por
posição, o que é frágil.

Para que o campo ganhe um `<input>` próprio (em vez de ser fundido com um
ancestor), é preciso:

```dart
Semantics(
  identifier: 'login-email',
  container: true,
  explicitChildNodes: true,
  textField: true,
  child: TextFormField(...),
)
```

## Regra de ouro

**O Playwright é excelente para o Flutter web no que é bom no browser**
(arranque, ausência de erros de consola, tráfego de rede, conteúdo
exposto) e **não substitui o `flutter test`** para a lógica de interface. A divisão
saudável é: `flutter test` para o comportamento, Playwright para a
integração real.

## Ver

`tests/Playwright/test_flutter_app.py` — os nove testes que ensinam isto.
