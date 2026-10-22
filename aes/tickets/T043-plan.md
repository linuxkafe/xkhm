---
ticket: T043
phase: plan
status: done
created: 2026-10-22
tier: heavy
verdict: pass
requires:
  - aes/tickets/T043.md
produces:
  - aes/tickets/T043-plan.md
blocked_by: ''
---

# T043 — Plan: A gestão de produtos, serviços e servidores

## Two-Agent Analysis (heavy tier)

### Critic Phase

```
## Como se fosse uma criança
A loja tem prateleiras com coisas para vender, cada uma com um
preço. E tem as máquinas onde as coisas ficam guardadas. Antes não
se podia mexer em nada disso: só se via. Agora pode-se pôr uma
prateleira nova, mudar o preço, e dizer que a coisa está numa
máquina em vez de outra.

## Como se fosse um especialista

[FAILURE MODE 1] — O `INSERT` parcial é recusado pelo MySQL, e o
MySQL tem razão.

Mecanismo: as tabelas do WHMCS são largas, e quase todas as colunas
são `NOT NULL` **sem default**. O `INSERT` com meia dúzia de
colunas falha com `ERROR 1364: Field 'description' doesn't have a
default value` — e falha **em silêncio**, do ponto de vista do
operador: o formulário fica lá, e o produto não aparece.

Mitigation: as colunas que a tabela exige e que o painel não expõe
são lidas **do esquema** (`information_schema`) e preenchidas com o
valor neutro do seu tipo. O esquema é a fonte da verdade, e uma
coluna acrescentada depois não quebra o insert. As colunas cujo
valor neutro seria uma **mentira** — as enumerações e as datas — são
deixadas de fora de propósito: uma que falte tem de falhar em voz
alta.

[FAILURE MODE 2] — `order` é uma palavra reservada.

Mecanismo: `tblproducts` tem uma coluna `order`. Um `INSERT` que a
liste sem crases é um erro de sintaxe — e o erro aparece longe da
causa.

Mitigation: todos os identificadores dinâmicos vão entre crases.

[FAILURE MODE 3] — O formulário dos grupos criava um **produto**.

Mecanismo: o formulário do grupo e o do produto iam para o mesmo
caminho, e o `action` do grupo não era tratado. Um «Nome do grupo»
subido criava um produto chamado «Nome do grupo» — e um produto
falso no catálogo.

Mitigation: cada formulário tem a sua `action`, e cada `action` faz
**uma** coisa. O formulário dos preços deixou de repetir os campos
do produto (a ambiguidade dos dois `name` na mesma página), e o
`id` de um produto novo abre-se logo para os preços.

[FAILURE MODE 4] — Remover um produto que um cliente tem.

Mecanismo: o `tblhosting` aponta para o produto (`packageid`). Remover
o produto deixa o serviço do cliente apontando para o nada — e o
serviço passa a ser «sem produto», que é invisível em todo o lado.

Mitigation: antes de remover, conta-se os serviços. Se houver
algum, o produto **fica**, e o painel diz porquê e sugere escondê-lo
(o `hidden`, que o WHMCS também tem).

[FAILURE MODE 5] — A palavra-passe do servidor sai na página.

Mecanismo: `tblservers.password` é a credencial da máquina. A lista
não a mostra — mas o formulário tem de a mostrar para poder ser
editada, e o campo tem de ser `password`, não `text`.

Mitigation: o campo é de tipo `password`; a lista não mostra a
senha; e o teste verifica o **tipo** do campo, não o seu valor.

[ASSUMPTION]
Se falso: se `tblhosting` não for a tabela dos serviços, a
contagem de serviços por produto é errada — e a protecção da
remoção não protege.

Verificado contra o esquema: `tblhosting` tem `packageid`, e é onde
o `tblhosting` aponta para `tblproducts`. A primeira versão do código
usava `productid`, que **não existe** — e o `SELECT` rebentava com
um 500.

[ALTERNATIVE FRAMING]
Em vez de um ecrã de produtos e outro de servidores, uma única página
de «catálogo» com as duas coisas.

Rejeitado: as duas coisas têm ciclos de vida diferentes (um produto
vende-se, um servidor aloja) e o WHMCS põe-as em páginas separadas
(`configproducts`, `configservers`). O operador conhece essa
divisão.
```

## A solução

1. `src/Repository/CatalogueRepositoryInterface.php` +
   `CatalogueRepositoryPDO.php` — a leitura e a escrita, com o
   preenchimento neutro lido do esquema.
2. `src/Http/Controller/Admin/CatalogueController.php` — os
   produtos: lista, filtro por grupo, formulário, preços por ciclo,
   criação, remoção (protegida).
3. `src/Http/Controller/Admin/ServerController.php` — os servidores:
   lista, formulário, grupos, criação, remoção.
4. As rotas em `public/index.php` e as entradas no menu do painel.

## O que fica de fora

- **As opções de produto** (`tblproductoptions`, `configoption1..24`).
  São a página `configproductoptions` do WHMCS, e é um trabalho
  próprio.
- **A divisão da encomenda** — o que o cliente vê na loja. O painel de
  T043 trata do catálogo; a loja é outra conversa.
- **O aprovisionamento** — mandar aprovisionar o serviço no
  servidor. O WHMCS fá-lo com o módulo de automação, que depende
  de credenciais que não existem.
