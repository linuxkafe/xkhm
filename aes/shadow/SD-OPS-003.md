---
id: SD-OPS-003
title: "Um healthcheck que não pode falhar é um decorador"
tier: ops
epistemic_state: verified
confidence: 0.95
date: 2026-10-21
source: T032
tags: [security, docker, mysql, healthcheck, operations]
---

# Um healthcheck que não pode falhar é um decorador

## O que aconteceu

O `docker-compose.yml` do xkhm tinha:

```yaml
healthcheck:
  test: ["CMD", "mysqladmin", "ping", "-h", "localhost",
         "-u", "linuxkonect", "-pqm9W8amh5XV9A4Dr"]
```

Duas coisas erradas ao mesmo tempo:

1. usava a senha de **produção** contra a base **local**;
2. `mysqladmin ping` **devolve exit 0 mesmo quando a autenticação
   falha**.

O contentor esteve "healthy" durante meses. A medição:

```
$ mysqladmin ping -h 127.0.0.1 -u linuxkonect -pSENHA_ERRADA
  exit=0        ← verde, com a senha errada
```

## Porquê

`mysqladmin ping` pergunta "o servidor está vivo?". O servidor
responde **antes** de autenticar — a autenticação é um passo
seguinte. O comando vê uma resposta e diz que está tudo bem.

Um healthcheck que só verifica a *viabilidade* não verifica a
*autenticabilidade*. E a autenticabilidade é exactamente o que se
quer vigiar numa base de dados.

## A correcção

```yaml
test:
  - CMD-SHELL
  - MYSQL_PWD="$$MYSQL_PASSWORD" mysql -h 127.0.0.1 -u "$$MYSQL_USER" \
    -e "SELECT 1" >/dev/null 2>&1
```

```
$ MYSQL_PWD="SENHA_ERRADA" mysql -h 127.0.0.1 -u linuxkonect -e "SELECT 1"
  exit=1        ← vermelho, como deve ser
$ MYSQL_PWD="$MYSQL_PASSWORD" mysql -h 127.0.0.1 -u linuxkonect -e "SELECT 1"
  exit=0
```

`MYSQL_PWD` em vez de `-p...` na linha de comando: a senha não fica
visível no `ps` de toda a gente.

## A regra

> Um healthcheck tem de **exercitar a coisa que quer vigiar**.
> Se a falha que ele deveria detectar é a que ele não detecta, ele
> não é um healthcheck — é um decorador que dá confiança falso.

E o corolário, que vale para todo o sistema: **um teste que nunca
falhou não é um teste que passou; é um teste que não mede nada.** A
pergunta a fazer a qualquer verificação é "que falha é que ela apanha
— e eu já vi essa falha apanhada?".

Ver também: [[SD-OPS-001]] (a porta de base de dados publicada), que
é a outra metade deste incidente — o mesmo segredo, do lado de fora.
