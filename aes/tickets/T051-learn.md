---
ticket: T051
phase: learn
status: done
created: 2026-10-22
requires:
  - aes/tickets/T051-verify.md
---

# T051 — Learn: O login no telefone, e os restantes campos

## O que se aprendeu

### 1. A wrapper de `Semantics` à volta de um campo quebra o foco

A wrapper (com `container: true`, `explicitChildNodes: true`,
`textField: true`) é a causa do defeito: no web, no Android, o
teclado perde o foco a cada tecla quando o nó de semântica é
recriado.

**Aprendido:** a `Semantics` é para a **acessibilidade**, e não para
o **teste**. A `key` e a `labelText` são suficientes para os
testes; a wrapper, em excesso, **quebra** a experiência real.

E o defeito não foi apanhado por nenhum teste — porque os testes
correm no navegador do Playwright, que não tem teclado suave, e o
foco não se perde. Só um dispositivo real o apanha.

**Aprendido:** há defeitos que só existem num **dispositivo real**.
Um teste automatizado que não os apanha não é um teste suficiente
— e a **ficha do utilizador** («o login perde o foco em cada
letra») é o teste que faltava. A ficha do operador é o teste de
regressão.

### 2. O `textInputAction` é parte da experiência

Sem ele, o teclado do telefone não sabe que tecla mostrar: uma seta
para o campo seguinte, ou um botão de envio. E o `keyboardType`
define o teclado (o numérico para um código, o de URL para um
domínio).

**Aprendido:** os campos de um formulário não são só **o que**
aceitam: são **como** o teclado os apresenta. E a diferença entre
um formulário que se usa e um que se sofre está nessa tecla.

### 3. `autofillHints` e `autocorrect`: o que o telefone sabe

O e-mail não deve ter sugestões (é uma credencial, e a sugestão
pode ser a de outro utilizador). O código é só dígitos. O domínio é
uma URL.

**Aprendido:** os campos de um formulário não são todos iguais, e o
teclado do telefone **sabe** isso — se lhe dissermos.

### 4. O `LayoutBuilder` reconstrui-se a cada mudanca de restricao

A primeira tentativa removeu a wrapper de `Semantics` e usou um
`LayoutBuilder` para centrar o conteudo. O `LayoutBuilder` reconstrui
**a cada mudanca de restricao** — e a mudanca de restricao e' o que
acontece quando o teclado aparece. Era a reconstrucao que fazia o
foco perder-se.

**Aprendido:** o `LayoutBuilder` e' para o layout que depende das
restricoes. Um formulario que nao deve saltar com o teclado nao
precisa dele: o `SingleChildScrollView` com o padding do teclado e'
suficiente, e o conteudo fica **ao topo**.

E a segunda tentativa (esta) e' a que o operador tem de testar no
dispositivo.
