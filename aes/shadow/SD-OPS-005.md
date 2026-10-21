---
id: SD-OPS-005
title: "Quando a rede separa dois componentes, move-se o componente"
tier: ops
epistemic_state: verified
confidence: 0.9
date: 2026-10-21
source: T032
tags: [docker, networking, mail, operations]
---

# Quando a rede separa dois componentes, move-se o componente

## O que aconteceu

A aplicação corre num contentor. O servidor de correio (Postfix) corre
no host, na porta 25, e aceita submissões do loopback sem
autenticação. O contentor **não chega ao host**:

```
contentor → 172.28.0.1:25    timeout
contentor → 172.28.0.1:53    timeout
contentor → 10.0.0.111:25    timeout
```

O firewall do host rejeita o tráfego da bridge Docker, e não há `sudo`
para o abrir.

A solução tentadora era `network_mode: host` — mas a porta 80 do host
está ocupada, e partilhar a pilha de rede do host traria de volta a
colisão do MySQL do host na 3306 (ver [[SD-OPS-001]]).

A solução que funciona: **mover a entrega para o host**.

```
a aplicação    →  escreve a fila (um ficheiro)
correio do host →  lê a fila, entrega, apaga
```

O `crontab` do utilizador corre o correio de minuto a minuto. Nenhum
dos dois componentes conhece o outro.

## A regra

> Quando a rede separa dois componentes, a pergunta não é "como abro a
> porta?" mas "**de que lado tem de ficar cada coisa?**".

A aplicação produz o token. O host, que tem o servidor de correio,
entrega-o. Um **ficheiro** é a fronteira — e é uma fronteira que
funciona em qualquer rede, sem portas abertas.

## A regra de posse, que é o que evita tudo

> O ficheiro é a **fila**. Quem entrega **retira-o** da fila.

Sem esta regra há duas formas de falhar, e as duas são bugs de
produção:

- **entrega duplicada** — a aplicação entrega e o correio do host
  também (dois consumidores);
- **token perdido** — a aplicação apaga o ficheiro depois de entregar,
  mas o correio falha a meio (zero consumidores).

E quando a entrega falha, o ficheiro vai para `failed/` **com o
motivo**: nada se perde, e o operador vê porque.

## A alternativa que não foi usada, e porquê

**SMTP autenticado a partir do contentor.** Não é possível: o contentor
não chega ao host. E se a aplicação corresse na máquina do WHMCS (sem
contentor), o `localhost:25` do contentor seria o contentor, não o
host — a mesma confusão, com mais syllables.

Ver também: [[SD-OPS-004]] (as credenciais), [[SD-OPS-003]] (o
healthcheck que não falha).
