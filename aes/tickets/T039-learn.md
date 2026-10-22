---
ticket: T039
phase: learn
status: done
created: 2026-10-21
requires:
  - aes/tickets/T039-verify.md
---

# T039 — Learn: A configuração

## O que se aprendeu

### 1. A configuração do WHMCS **é** a `tblconfiguration`

O `configuration.php` do WHMCS tem catorze linhas: a ligação, a licença,
o hash. O resto — **235 definições** — está na base de dados. E foi
por isso que a base importada as trouxe.

**Aprendido:** um ficheiro de configuração e uma tabela de
configuração são a mesma coisa em disguise. A do WHMCS é a tabela,
porque a configuração **muda com a instalação** (um produto novo, um
gateway, um preço). A aplicação tinha ficado com o ficheiro, e por
isso não sabia o nome da empresa — que estava na base, à espera.

### 2. A mesma coisa pode estar em dois sítios

A empresa estava em `CompanyAddress1` (vazio) e em
`RegistrarAdminAddress1` (preenchido). Não era um erro: o WHMCS guarda
a empresa **duas** vezes, porque o registador pode facturar ao cliente
com os **seus** dados (`RegistrarAdminUseClientDetails = on`).

**Aprendido:** a aplicação que migra de um sistema tem de saber
**onde** cada coisa vive, e de onde vem quando o sítio óbvio está
vazio. A **resolução** (`Company*` → `RegistrarAdmin*`) é uma regra
doWHMCS, e está escrita como tal — não como uma lista de campos.

### 3. `ON DUPLICATE KEY UPDATE` numa tabela **sem chave única** é um `INSERT` disfarçado

A `tblconfiguration.setting` é um `text` com um índice **não-único**. O
`INSERT ... ON DUPLICATE KEY UPDATE` **nunca** dispara: cada escrita
criava uma linha nova. E a base importada **já trazia** duplicados
(`OrderDaysGrace`, `ToggleInfoPopup`).

**Aprendido:** o `ON DUPLICATE KEY` depende de uma chave única que
**não existe** — e falha em silêncio, sem erro nem aviso. A escrita
correta é a do WHMCS: `UPDATE`, e só se não houver linha, `INSERT`.
E, como a tabela não tem `id`, a forma de deixar **exactamente uma**
linha é apagar e escrever.

O sintoma foi bonito: a numeração das faturas devolvia sempre `1`, e
ninguém sabia porquê.

### 4. A excepção da numeração tinha de ser explícita

`InstanceID`, `License` e `token_namespace` mudá-las pode invalidar a
instalação. A lista do que **se pode** editar é escrita à mão, e a
exclusão é igualmente visível.

**Aprendido:** uma lista de excepções é mais segura do que uma lista
de permissões implícita: o que **não** está na lista não se mexe, e
alguém que lê o código vê a fronteira.

### 5. O que falta é tão útil como o que há

O painel mostra as 235 definições — agrupadas, que é o que as torna
legíveis. Mas a primeira coisa que interessa é outra: **o que falta**
para o documento de pagamento ficar completo.

**Aprendido:** uma lista de configuração é o **inventário**; um
relatório do que falta é o **diagnóstico**. O operador não precisa de
saber que há 235 definições — precisa de saber que falta o NIF, e
porquê (porque sem ele não há fatura).

## Decisões que merecem ser lembradas

| Decisão | Porquê |
|---------|--------|
| A `tblconfiguration` é a fonte da verdade | É onde o WHMCS guarda, e a base importada trouxe-as |
| O `configuration.php` fica com a ligação | O que é da **ligação**, não do negócio |
| A empresa resolve `Company*` → `RegistrarAdmin*` | É a regra do WHMCS, e é onde os dados estão |
| A escrita apaga e escreve | A tabela não tem chave única; e fica uma linha |
| A lista das editáveis é explícita | A exclusão é auditável |
| O relatório do que falta vem primeiro | O diagnóstico vale mais do que o inventário |

## O que fica para depois

- As **restantes** 235 definições editáveis (por agora, só as que
  interessam).
- A **validação** das definições (um NIF com letras, um código postal
  errado).
- Os **templates de e-mail** do WHMCS (`EmailGlobalHeader`,
  `EmailGlobalFooter` estão na base e não são usados).
