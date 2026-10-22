---
ticket: T047
phase: learn
status: done
created: 2026-10-22
requires:
  - aes/tickets/T047-verify.md
---

# T047 — Learn: A interface de 2026 — o tipo de letra e o logótipo

## O que se aprendeu

### 1. Uma fonte **nomeada** não é uma fonte **servida**

O token dizia `--font-base: "Inter, system-ui, -apple-system, sans-serif"`.
Com as aspas, é **um** nome: o navegador procurava uma família
chamada `Inter, system-ui, -apple-system, sans-serif`.

**Aprendido:** a diferença entre *declarar* e *servir*. Uma
declaration `@font-face` que ninguém pediu é uma declaration
inútil; e um token que nomeia uma família sem as servir é uma
mentira — a marca diz «Inter» e o ecrã mostra a serif do sistema.

E a mesma confusão estava no **Dart**: o `fontFamily` do Flutter
não é uma cadeia de recurso, é **uma** família. A app embute a
Inter desde o início e nunca a usou.

**Aprendido:** o mesmo valor, em duas línguas, tem **duas
gramáticas**. A cadeia de recurso é do CSS; no Flutter, a família é
uma e o resto é `fontFamilyFallback`. O token tem de guardar as
duas coisas — a família e a cadeia — e cada gerador traduz.

### 2. Nenhum teste apanhou o defeito — o ecrã apanhou

O CSS era válido. O Dart era válido. As asserções passavam. O
painel estava com a serif e a app com a Roboto — e nada dizia.

**Aprendido:** um teste que verifica a **existência** da regra não
verifica a sua **eficácia**. O teste que apanha isto é o que
pergunta «a fonte carregou?»:

```python
assert page.evaluate("document.fonts.check('16px Inter')")
```

E as capturas de ecrã são uma forma de teste — a mais antiga de todas, e
a que ninguém escreve.

### 3. A variante escura não é o logótipo invertido: é o logótipo
**recortado**

O logótipo tem três cores: o preto (a chávena e o «LINUX»), o
amarelo (o «KAFE» e o vapor) e o branco (o interior da chávena).

Para o cabeçalho escuro, a primeira ideia é inverter. A segunda
(inverter só o preto) deixa o interior da chávena **branco** — uma
chávena branca com o interior branco, ou seja, sem chávena.

A variante certa é: o preto passa a branco, e o **branco passa a
transparente**. O interior da chávena passa a ser o próprio fundo.

**Aprendido:** um logo com mais do que uma cor não tem «uma variante
escura» — tem **várias**, e a pergunta certa não é «que cor
troco?» mas «que cor **devo deixar ser o fundo**?». A transparência
é a resposta que não depende da cor de fundo.

### 4. O amarelo da marca estava em dois sítios, e nenhum era o
logótipo

O token: `#f8b400` (da folha de estilo do tema WordPress). O
logótipo: `#fbb202` (2026/01).

**Aprendido:** uma marca tem **uma** cor de destaque, e o token é o
lugar onde ela vive. A reconciliação tem de ser feita **contra a
fonte mais recente** — e o logótipo, que é o que o operador pediu
para aplicar, é precisamente essa fonte.

### 5. Rasterizar é uma resposta, não uma derrota

O Flutter não lê SVG sem uma dependência nova. O logótipo foi
rasterizado em PNG (com o Playwright, que já era uma dependência) —
com as duas variantes, e com transparência.

**Aprendido:** «preciso de SVG» não é o mesmo que «preciso de
`flutter_svg`». O custo de uma dependência nova (a revisão, o
tamanho, o risco) é real, e uma dependência que a suite **já**
tem resolve o problema.

E o PNG saiu **11 KB** para um logótipo de 7 KB em SVG — porque o
logótipo é quase todo cor plana, e o PNG comprime isso muito bem.
