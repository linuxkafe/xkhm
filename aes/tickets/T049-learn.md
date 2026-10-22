---
ticket: T049
phase: learn
status: done
created: 2026-10-22
requires:
  - aes/tickets/T049-verify.md
---

# T049 — Learn: A interface de 2026, ao estilo do design system

## O que se aprendeu

### 1. Um teste que verifica a **existência** não verifica a
**eficácia**

O CSS era válido. O Dart era válido. As asserções passavam. O painel
estava com a serif e a app com a Roboto — e nada dizia.

**Aprendido:** a diferença entre «a regra está lá» e «a regra
funciona». O `--font-base: "Inter, system-ui, …"` era uma regra que
estava lá, e que **não** funcionava: com as aspas, era um nome de
família que não existe.

O teste que apanha isto é o que pergunta «a fonte carregou?»:

```python
assert page.evaluate("document.fonts.check('16px Inter')")
```

E o mais antigo de todos os testes — o screenshot — é o que ninguém
escreve, mas o que o operador leu. A ficha que ele deu («o tipo de
letra continua a não ser o moderno») era o teste que faltava.

### 2. O mesmo valor, em duas línguas, tem **duas gramáticas**

O `fontFamily` do Flutter não é uma cadeia: é **uma** família. A
app embutia a Inter desde o início e nunca a usou — porque o token
dizia `"Inter, system-ui, -apple-system, sans-serif"`, e o Flutter
procurava uma família com esse nome.

**Aprendido:** o token tem de guardar a **família** e a **cadeia de
recurso**, e cada gerador traduz para a sua língua. O CSS escreve a
cadeia; o Dart escreve a família e a lista de recurso.

### 3. O amarelo é um **acento**, não um texto

`h1, h2, h3, h4` eram `var(--color-primary)` — o amarelo sobre
branco. O contraste é de 1.9:1, e o WCGA exige 4.5:1. Um título
amarelo sobre branco **não se lê**.

**Aprendido:** a paleta tem o amarelo, mas o amarelo é o **acento**
(o item activo, o botão primário, o anel de foco). O texto é a
cor do texto. A distinção é a mesma do design system: «use the color
palette above, not arbitrary colors» — e o amarelo, usado como texto,
é uma cor arbitrariamente colocada.

### 4. Um logótipo com várias cores não tem «uma variante escura»

O logótipo tem três cores: o preto (a chávena e o «LINUX»), o
amarelo (o «KAFE» e o vapor) e o branco (o interior da chávena).

A primeira ideia era **inverter**. A segunda (inverter só o preto)
deixava o interior da chávena branco — uma chávena branca com o
interior branco, ou seja, sem chávena.

A variante certa é: o preto passa a branco, e o **branco passa a
transparente**. O interior da chávena passa a ser o próprio fundo.

**Aprendido:** a pergunta certa não é «que cor troco?» mas «que cor
**devo deixar ser o fundo**?». A transparência é a resposta que não
depende da cor de fundo.

E o operador pediu uma coisa ainda melhor: o logótipo **sobre fundo
branco**, em vez da cor invertida. A marca fica **fiel** (o wordmark
preto é o wordmark preto), e o fundo branco é a placa.

### 5. As fontes **variables** não são necessárias para parecer moderno

O Inter variable é uma face que se **adapta** ao tamanho. A versão
estática (Regular, SemiBold, Bold) já é a Inter que o design system
nomeia.

**Aprendido:** uma dependência nova (a conversão para variable) não
se justifica quando a estática chega. E a conversão é o mesmo
padrão (uma fonte, duas saídas) — se um dia for preciso, é o mesmo
código.

### 6. O design system é a **forma**, e as cores são as nossas

O design system é o estilo «precisão, foco, sem decoração»: sem
sombras, com bordas; a escala 32/24/20/14/12; o espacamento de 4 px;
os componentes (botões, campos, cartões) com padrões definidos.

O que se **não** copia é a paleta (verde `#22c55e`, fundo escuro) —
as cores são as do LINUXKAFE (`#fbb202`, `#2d2d2d`).

**Aprendido:** um design system é uma **forma** e uma **paleta**. A
forma é partilhada; a paleta é a marca. Copiar as duas é copiar
outra marca; copiar a forma é modernizar a nossa.
