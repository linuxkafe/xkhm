---
id: SD-OPS-002
title: "O login falhava por causa do formato da senha, não da senha"
tier: ops
epistemic_state: verified
confidence: 0.9
date: 2026-10-21
source: T028
tags: [auth, passwords, whmcs, bcrypt, md5, operations]
---

# O login falhava por causa do formato da senha, não da senha

## O sintoma

"O login continua a não funcionar com o utilizador `linuxkafe`."

A primeira hipótese — e a errada — é que a senha estava errada. Não
estava. A senha estava certa e o **verificador** não sabia verificar.

## O que a base real guarda

| Tabela | Formato | Tamanho |
|--------|---------|---------|
| `tbladmins` | bcrypt `$2y$10$…` | 60 |
| `tblclients` | `<md5>:<sal de 5>` | 38 |

O `AuthService` só aceitava md5 de 32 e salt+md5 de 34. **Nenhum dos
dois formatos reais era suportado.** O mesmo utilizador, com a senha
certa, falhava — e a falha parecia uma senha errada.

## A armadilha

O formato do WHMCS 5.x mudou entre versões: o sal é misturado no hash
antes ou depois da password, com ou sem duplicação, com ou sem ponto. E
as **fontes** do WHMCS 5.3.8 estão cifradas com ionCube — não se lêem.

Sem um par (senha, hash) conhecido, o algoritmo **não é determinável
pelos dados**: todas as arranjos são igualmente consistentes consigo
mesmos. Uma senha forte resiste a qualquer dicionário (a do admin
bcrypt resistiu a 269 candidatos).

## A solução

**O verificador não adivinha: ele inspeciona.** Cada ramo é escolhido
pela *forma* do hash armazenado, e o ramo do formato salinado tenta
**todos** os arranjos plausíveis. São algumas md5 — o custo é nulo, e o
benefício é que a senha do operador funciona, seja qual for o arranjo.

E as senhas **novas** são sempre bcrypt: os formatos fracos só se
verificam, nunca se produzem.

## A lição

**"A senha está errada" e "não sei verificar esta senha" produzem o
mesmo sintoma.** Quando um login falha contra um sistema que já funcionou,
a primeira pergunta não é "qual é a senha" — é "**o que é que mudou no
formato**". Uma importação de base de dados pode mudar o formato sem
mudar uma única senha.

E a consequência operacional: um formato de hash antigo que ninguém
consegue identificar é um **bloqueio de produção** que só se resolve com
uma via que não dependa de adivinhar — neste caso, um caminho de reset
que escreva bcrypt.

## Ver

- `src/Service/PasswordHasher.php`
- `tests/Unit/PasswordHasherTest.php`
- `tests/Integration/PasswordFormatTest.php` — uma conta real por formato
