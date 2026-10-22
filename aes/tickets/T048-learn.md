---
ticket: T048
phase: learn
status: done
created: 2026-10-22
requires:
  - aes/tickets/T048-verify.md
---

# T048 — Learn: O tipo de interligação do servidor

## O que se aprendeu

### 1. A coluna mais importante não é a mais visível

O `tblservers.type` é o **módulo** que aprovisiona o servidor — o
plugin que cria a conta, configura o alojamento e trata da
renovação. Sem ele, o servidor é um nome de máquina.

E o formulário **não o mostrava**, e o repositório **não o
gravava**. A coluna estava no esquema, mas o código ignorava-a.

**Aprendido:** o esquema é a **lista do que importa**, e a
aplicação tem de estar em contacto com todas as colunas — não só
com as que a interface conhece. A coluna `type` é a prova disso:
estava lá, e ninguém a lia nem a escrevia.

### 2. O WHMCS não tem uma lista de módulos na base de dados

Os módulos vivem no **disco** (`modules/servers/`), e o WHMCS lê-os
de lá. O xkhm não tem esses módulos — não aprovisiona — mas pode
**oferecer** a lista, que é o inventário do WHMCS original.

**Aprendido:** quando a aplicação não é a fonte do dado, a **fonte
original** é. A lista foi gerada (`tools/extract_server_modules.py`)
a partir do `modules/servers/` — e a regra que a mantém honesta é a
mesma dos tokens e das etiquetas: uma fonte, uma saída.

### 3. O nome do módulo não é o identificador

`virtualmin` (o identificador) e `Virtualmin` (o nome) são
diferentes — e o `whmcs_multisite` é `WHMCS MultiSite`. O
identificador é o que a base de dados guarda; o nome é o que o
operador lê.

**Aprendido:** a base de dados guarda o **identificador**, e a
interface mostra o **nome**. O mapa entre os dois é escrito à mão,
porque os módulos são cifrados — e isso é honesto, porque diz
exactamente o que é e o que não é.
