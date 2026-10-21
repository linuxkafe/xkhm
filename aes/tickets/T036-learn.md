---
ticket: T036
phase: learn
status: done
created: 2026-10-21
requires:
  - aes/tickets/T036-verify.md
---

# T036 — Learn: A interface mobile, modernizada

## O que se aprendeu

### 1. "Modernizar" sem medida é gutar

O pedido era vago. O que não era vago era a **captura**: no telefone,
o ecrã de início tinha um número e meio de informação visível.

**Aprendido:** um ticket de interface começa pela **medição**, não pela
opinião. "As figuras estão empilhadas porque têm 220 px fixos e o
ecrã útil tem 342" é um facto; "o cartão é grande demais" é uma
opinião. E a verificação tem de ser um **teste**: as figuras lado a
lado, a primeira secção acima de um terço, o texto cortado a três
linhas.

### 2. A largura fixa é a raiz de quase todo o layout mau

`SizedBox(width: 220)` num ecrã de 390 px, com 24 px de margem, deixa
342 px úteis: **um** tile cabe, dois não. Por isso empilhavam — e
empilhados, ocupavam o ecrã inteiro.

**Aprendido:** a largura fixa num ecrã de largura variável é um
problema à espera. O `Expanded` diz "divide o que houver", e o
conteúdo decide o resto. E a **mesma** regra dos tokens vale para os
dois lados: `--width-max` no CSS, `LkLayout.widthMax` no Dart.

### 3. O `dart format` é uma decisão, não um detalhe

Formatei os ficheiros que toquei e o formatador reformatou **código
não relacionado** — o projecto não estava formatado com ele. O diff
passou de 6 linhas para 57, e o que interessava ficou enterrado.

**Aprendido:** `dart format` num projecto que não está formatado
**reescreve o projecto inteiro**. Ou se formata tudo (uma decisão,
com um commit próprio), ou não se formata nada. A meio caminho — o
que eu fiz — o pior dos dois: um diff que ninguém consegue ler.

### 4. Um teste que passa pelo motivo errado

Uma asserção antiga verificava `assert "linuxkafe" in body.lower()`.
O comentário dizia «a identidade vem da linha real do cliente» — mas
o "linuxkafe" que aparecia era a **marca no fim do anúncio** («Obrigado
LINUXKAFE»), e não a identidade do cliente.

Só falhou quando o resumo passou a cortar o anúncio. Ou seja: **a
asserção estava a proteger a coisa errada**, e ninguém notou porque
passava.

**Aprendido:** um teste que passa pelo motivo errado é pior do que
um teste que não existe — protege do erro errado. E quando um teste
antigo falha depois de uma mudança, a **primeira** pergunta não é
"o que é que quebrei?" mas "**o que é que este teste estava a
verificar, afinal?**".

### 5. O `inner_text` só chega aos filhos directos

O texto do anúncio desaparecia da leitura do teste. Não tinha
desaparecido da aplicação: o Flutter **funde** textos adjacentes num
único nó de semântica, e esse nó vive mais fundo na árvore. O
`inner_text` do host só devolve o texto dos filhos **directos**.

**Aprendido:** para ler uma árvore de acessibilidade, lê-se a
**árvore** — os rótulos de todos os nós — e não o `inner_text` de um
elemento. A diferença entre "o texto sumiu" e "o texto mudou de
nó" é a diferença entre um bug de acessibilidade e um teste mal
escrito.

### 6. O resumo é visual; a acessibilidade é total

O `Text` com `maxLines` e `ellipsis` corta **visualmente** — mas o nó
de semântica carrega o **texto todo**. Quem lê com um leitor de ecrã
não perde o fim do anúncio.

**Aprendido:** o corte é uma cortesia para quem lê com os olhos; o
«ler mais» existe por causa disso. E o **botão só aparece quando o
corte acontece de facto** — medido com um `TextPainter`, não adivinhado
por um número de caracteres. Um «ler mais» que abre nada é pior do
que não ter botão.

### 7. O `fill()` não funciona no Flutter — e é intermitente

O login falhava uma vez em três. A causa: `fill()` escreve o valor de
uma vez, e no Flutter a **primeira tecla perde-se** na transição de
foco. Metade das vezes ficava metade da senha.

**Aprendido (terceira vez, e a lição é sempre a mesma):** num campo
do Flutter, ou se **foca, limpa e escreve com um atraso**, ou não
funciona. E a falha é **intermitente** — o pior tipo, porque uma vez
em três passa, e o teste passa a ser «normalmente verde».

## Decisões que merecem ser lembradas

| Decisão | Porquê |
|---------|--------|
| O herói usa o gradiente dos **tokens** | O modo escuro tem de vir com ele |
| As figuras em `Expanded` | A largura fixa é a raiz do layout mau |
| O `LkStatTile` **não** mudou | O widget é partilhado; muda a largura com que o ecrã o coloca |
| O «ler mais» só aparece se o texto for cortado | Medido com `TextPainter` |
| O resumo corta **visualmente**, não semanticamente | Quem lê com leitor de ecrã não perde nada |
| O pull-to-refresh só nos ecrãs **com dados** | Um gesto vazio é pior do que nenhum gesto |
| O `dart format` não se aplica a meio caminho | Ou se formata o projecto, ou não se formata |

## O que fica para depois

- **As animações** de transição entre ecrãs.
- **O esqueleto de carregamento** (shimmer) em vez do `LkLoading`.
- **As telas de detalhe** modernizadas pela mesma lógica — a lista de
  faturas e a de tickets ainda usam o cartão inteiro.
- A **barra de navegação** com indicador já é a do Material 3; o que
  falta é a mesma atenção nos **cartões** (as bordas e as sombras
  podiam ser mais discretas num ecrã pequeno).
