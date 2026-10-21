---
ticket: T035
phase: learn
status: done
created: 2026-10-21
requires:
  - aes/tickets/T035-verify.md
---

# T035 — Learn: Os serviços do cliente, pelo seu tipo

## O que se aprendeu

### 1. O nome de uma tabela não é o que está lá dentro

`tblhosting` chama-se "hosting". A interface mostrava "Alojamento". Mas
a tabela tem **todos** os serviços — e 3 dos 10 eram de **Backup**.

**Aprendido:** o rótulo que se mostra ao utilizador vem da **semântica
do negócio**, não do nome da tabela. O nome da tabela é a implementação;
o grupo do produto é o conceito. Quando os dois divergem — e divergem
— o que está errado é o rótulo.

E a pergunta que o operador fez ("não vejo os serviços... estão todos
definidos como alojamento web") era a pergunta certa: **ele viu que a
informação estava a ser partida, e tinha razão**.

### 2. O tipo que a máquina guarda não é o tipo que a pessoa lê

`tblproducts.type` tem três valores: `hostingaccount`, `other`,
`server`. Mostrar "other" a uma pessoa é mostrar o esquema.

**Aprendido:** um valor **bruto** e um **rótulo** são coisas
diferentes, e as duas têm de viajar. O `rawType` serve ao código; o
`type` (o grupo) serve a quem lê. A interface escolhe — e o API devolve
os dois, para que a decisão não seja forçada a quem consome.

### 3. Mostrar um campo vazio é mostrar um dado falso

Um backup não tem domínio, não tem utilizador, não tem disco. Uma
tabela com as mesmas colunas para todos os tipos tem meia dúzia de
células vazias — e uma célula vazia lê-se como "o valor é desconhecido",
não como "o campo não se aplica".

**Aprendido:** a interface mostra **o que o serviço tem**. A pergunta
não é "que campos tem um serviço?" mas "**que campos tem este
serviço?**". E a resposta está nos dados, não no esquema.

### 4. A semente é parte da funcionalidade

A conta de teste não tinha serviços. A funcionalidade estava pronta, e
não havia nada que mostrar — nem que verificar. A diferença que o
operador pediu ver era **invisível** nos dados de desenvolvimento.

**Aprendido:** quando a funcionalidade é sobre uma **distinção**, os
dados de teste têm de conter a distinção. Uma semente que cria só o
caso fácil esconde o bug e faz o teste passar sem verificar nada.

### 5. O `inner_text` devolve o que é *renderizado*

O CSS aplica `text-transform: uppercase` aos títulos. O `inner_text`
devolve "SERVIÇOS", e a asserção procurava "Serviços".

**Aprendido (segunda vez, e a segunda vez dobra):** o texto que o
Playwright lê é o **texto renderizado**, não o do código. Toda a
comparação com maiúsculas tem de ser insensível — ou o teste tem de
procurar o que é realmente escrito.

E o mesmo vale para o `flt-semantics-host`: a árvore existe, mas está
**oculta** (à espera de um leitor de ecrã). `wait_for_selector` sem
`state="attached"` espera por algo que nunca fica visível.

### 6. O viewport do `conftest` é o do desktop

O `page` do `conftest` usa 1280×900. A 1280 px a app mostra a **rail**
de navegação; o menu — onde os serviços estão — só existe no telefone.

**Aprendido:** um teste de interface **móvel** tem de dizer isso. O
viewport herdado é o do desktop, e o teste herda-o em silêncio. Um
teste que passa no desktop e falha no móvel (ou o contrário) está a
testar a coisa errada.

## Decisões que merecem ser lembradas

| Decisão | Porquê |
|---------|--------|
| O rótulo é o **grupo** do produto | É o que o WHMCS mostra ao cliente; `other` não é um tipo |
| O `rawType` viaja ao lado | A máquina precisa dele, e a decisão fica de quem consome |
| O tipo vem de uma **junção** | Uma lista de tipos escrita à mão desincroniza-se |
| A interface mostra só o que o serviço tem | Uma célula vazia lê-se como "desconhecido" |
| A semente cria serviços de duas famílias | A distinção tem de existir nos dados para ser vista e testada |
| O ecrã de serviços é no **menu** | Um ecrã que não se chega a partir de lado nenhum não existe |

## O que fica para depois

- Criar, alterar e cancelar serviços (é trabalho de produto, não de
  leitura).
- A faturação dos serviços — o `CreditService` já sabe aplicar crédito
  a uma fatura, mas a transação falta (ver a dívida do T034).
- Os **detalhes** de cada tipo de serviço em particular: um VPS tem
  recursos que um alojamento não tem, e a tabela `tblvps` não existe
  nesta base.
