---
ticket: T043
phase: learn
status: done
created: 2026-10-22
requires:
  - aes/tickets/T043-verify.md
---

# T043 — Learn: A gestão de produtos, serviços e servidores

## O que se aprendeu

### 1. As tabelas do WHMCS são **largas e obrigatórias**

`tblproducts` tem 70 colunas `NOT NULL` sem default. O `INSERT` com
as colunas que o painel conhece falha com `ERROR 1364` — e falha em
silêncio, do ponto de vista de quem usa o painel: o formulário
aceita, a página responde, e o produto não existe.

**Aprendido:** o `INSERT` de um produto tem de preencher a tabela
**inteira**. A lista das colunas não se escreve à mão: lê-se do
esquema (`information_schema`), e o que falta toma o valor neutro do
seu tipo. A excepção são as colunas cujo valor neutro seria uma
**mentira** — as enumerações e as datas — que ficam de fora de
propósito, para que a ausência seja um erro **falante** e não um
valor silenciosamente errado.

### 2. `order` é uma palavra reservada, e `packageid` não é `productid`

O `INSERT` falhou com um erro de sintaxe «longe» da causa, e a
conta de serviços por produto rebentou com um 500, porque
`tblhosting` **não tem** `productid` — tem `packageid`, como no
WHMCS.

**Aprendido:** os nomes de coluna do WHMCS são os do WHMCS, e o
esquema é a única testemunha fiável. A inferência pelo nome
("deve ser `productid`") é uma hipótese — e custou um 500.

### 3. Um formulário por acção, e o `action` que diz qual

O formulário do grupo de produtos e o do produto iam para o mesmo
caminho, e o `action` do grupo não era tratado: subir «Nome do
grupo» criava um **produto** chamado «Nome do grupo».

**Aprendido:** quando dois formulários partilham um caminho, o
`action` tem de ser tratado **antes** de tudo o resto, e cada
`action` faz **uma** coisa. O formulário dos preços deixou de
repetir os campos do produto — o que, de passagem, tirou a
ambiguidade dos dois campos `name` na mesma página.

### 4. Remover é mais perigoso que criar

Criar um produto errado faz-se com um clique. Remover um produto
que um cliente tem deixa o serviço dele apontando para o nada — e o
serviço fica invisível em todo o lado, sem erro nenhum.

**Aprendido:** antes de remover, conta-se quem depende. Se houver
alguém, o produto fica e o painel **diz porquê** e sugere a
alternativa do WHMCS (esconder). Um botão que recusa com uma
explicação é melhor do que um botão que recusa sem nada.

### 5. A palavra-passe do servidor é uma credencial, e o formulário tem de a mostrar

O campo tem de ser `password` (para não ficar nos ombros do
operador) mas tem de **trazer** o valor (para poder ser corrigido).
E a **lista** não mostra a senha — mostra o hostname, o IP, o custo
e o estado.

**Aprendido:** uma credencial tem duasOverflow faces: a de
escrita (protegida) e a de leitura (ausente). O teste verifica o
**tipo** do campo, não o seu valor.
