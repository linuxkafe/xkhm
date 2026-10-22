---
ticket: T046
phase: learn
status: done
created: 2026-10-22
requires:
  - aes/tickets/T046-verify.md
---

# T046 — Learn: O cron — e os avisos que não saem para os clientes

## O que se aprendeu

### 1. A restrição do operador é um **requisito**, e um requisito com forma

«Não convém disparar para clientes até validação» não é um
pedido de estilo: é uma condição de segurança. E a forma de a
implementar **não** é uma convenção («não mexer nesses clientes»),
é **dois portões**:

- a aplicação não endereça o aviso ao cliente;
- o correio recusa o aviso, mesmo que ele entre na fila por engano.

**Aprendido:** uma restrição que depende de ninguém se esquecer é
uma restrição que falha no dia em que alguém se esquece. O segundo
portão é o que a torna uma propriedade do sistema, e não uma
promessa.

E a lista (uma só, no ambiente) é a **fonte única** para os dois
runtimes — o contentor e o host. Sem ela, as duas implementações
divergem, e divergem em silêncio.

### 2. A política e a acção são coisas diferentes

`AutoSuspension` está `on` na instalação: a política do WHMCS é
suspender os serviços em atraso. Mas uma instalação **não validada**
não pode agir sobre essa política — o dano (o cliente sem serviço) é
irreversível.

**Aprendido:** a definição diz **o que** deve ser feito; o ambiente
diz **se** pode ser feito. Separar as duas coisas permite ter a
política do WHMCS e, ao mesmo tempo, não a exercer.

### 3. O idempotência mora nos **dados**, não no código

O guard da renovação é o **item da fatura**, que carrega o
vencimento do período. A pergunta «esta fatura já existe?» é
respondida pela base de dados, e não por um marcador no código.

**Aprendido:** um guard que depende do estado do processo (um
`if` num ficheiro, um lock) falha quando o processo é reiniciado. Um
guard que depende dos **dados** sobrevive a tudo — porque a base de
dados é o que não se perde.

E o mesmo se aplica aos avisos: o registo de actividade é o guard, e
o guard e a escrita têm de usar **a mesma frase** (a palavra
«enfileirado» no fim da frase de registo foi o que fez o aviso sair
duas vezes).

### 4. O `sql_mode` é parte do **esquema**

O MySQL local era mais restrito que o MySQL da produção: o
`NO_ZERO_DATE` recusava a data zero — e a data zero é a convenção do
WHMCS para «ainda não há data». Os dados **importados** têm isso.

**Aprendido:** o `sql_mode` não é uma configuração do servidor: é
parte do **contrato** com a aplicação. Um dump importado carrega
esse contrato, mesmo que ninguém o escreva em lado nenhum. A
pergunta a fazer de uma base importada não é «que colunas tem?»,
mas «que convenções usa?».

### 5. Um directório de bind mount tem **dois** donos

O `storage/` é um bind mount: o contentor escreve como `www-data`, o
host escreve como `seyon`, e o correio lê. O `entrypoint` prepara os
directórios para o contentor — mas o `notice-pdfs` foi criado depois
(T038) e o `entrypoint` **nunca soube dele**. Resultado: o host não
conseguia escrever, a fila falhava, e a falha era **silenciosa** (a
fila devolvia `false`, e nada dizia).

**Aprendido:** um bind mount entre dois utilizadores tem de ser
preparado **para os dois**, e a lista dos directórios tem de estar
num sítio único. A entrada que faltava foi exactamente a que o
`entrypoint` não conhecia — e nenhuma mensagem de erro apareceu.

## O que fica para o futuro

- A **terminação** dos serviços (`AutoTermination`, desligada).
- A **sincronização dos domínios** com os registadores.
- A **facturação de tickets**.
- O **`Upgrade`** automático.
- O **ciclo por hora** vs. diário: o WHMCS recommends uma vez por
  dia, às 9h, e é o que está instalado.
