---
id: SD-OPS-001
title: "Uma porta de base de dados publicada é uma porta aberta"
tier: ops
epistemic_state: verified
confidence: 0.95
date: 2026-10-21
source: T027
tags: [security, docker, mysql, incident, operations]
---

# Uma porta de base de dados publicada é uma porta aberta

## O que aconteceu

A base de dados local do xkhm foi apagada e substituída por uma nota de
resgate. A nota dizia:

```
This address is assigned to your database credentials (host + user:
94.61.137.133 + root).
```

`94.61.137.133` era o IP público da máquina. A causa estava no
`docker-compose.yml` que eu próprio escrevi:

```yaml
ports:
  - "3306:3306"                        # 0.0.0.0:3306
environment:
  - MYSQL_PASSWORD=<a senha de produção — rodada; ver aes/tickets/T032.md>    # a senha de PRODUÇÃO
```

Duas decisões separadas, cada uma inofensiva, que juntas abriram a base de
dados à Internet:

1. **a porta é publicada em todas as interfaces** — o `ports` do Docker
   publica em `0.0.0.0` por omissão, mesmo numa máquina "de développement";
2. **a senha é a de produção** — e a de produção estava no histórico do
   git, num ficheiro versionado, e no `configuration.php`.

Um scanner encontra portas 3306 abertas em minutos. A partir daí, a senha
conhecida é o resto do caminho.

## A regra

**A base de dados de desenvolvimento nunca usa a senha da de produção.**

Não é uma questão de "é a mesma aplicação". A senha de produção está no
histórico do git — que é público para sempre. Usá-la localmente significa
que qualquer pessoa com acesso ao repositório tem acesso à base de
produção, se encontrar a porta.

A separação tem de ser **estrutural**, não uma convenção:

```yaml
environment:
  - MYSQL_PASSWORD=${XKHM_DB_PASSWORD:-xkhm-local-only}
ports:
  - "127.0.0.1:3306:3306"   # só o loopback
```

E a `configuration.php` passa a aceitar o override:

```php
$cfg['db_password'] = getenv('DB_PASSWORD') ?: '<a senha de produção — rodada; ver aes/tickets/T032.md>';
```

### O detalhe do Apache

`clear_env On` é o omissão do Apache: as variáveis de ambiente do
contentor **não chegam ao PHP**. Sem isto, o override nunca funciona e
ninguém percebe porque é que a senha "não tem efeito":

```apache
PassEnv DB_PASSWORD
```

## A verificação que faltava

```bash
docker port xkhm-mysql-1
# 3306/tcp -> 0.0.0.0:3306     ← RED
# 3306/tcp -> 127.0.0.1:3306   ← OK
```

E, a partir de fora:

```bash
timeout 5 bash -c 'cat < /dev/null > /dev/tcp/94.61.137.133/3306'
# Connection refused
```

## O que NÃO fazer

**Não pagar.** A nota é um modelo de negócio, não uma operação. A base de
dados local era um *fixture* de desenvolvimento — o valor real (a base de
produção) nunca esteve lá. E a senha que o atacante "confirmou" é a de
produção, que tem de ser rodada de qualquer maneira, incidente ou não.

## A lição

**Um `ports:` no compose é uma decisão de segurança, não de conveniência.**
Cada porta publicada é uma porta da Internet. A pergunta a fazer não é
"preciso de aceder a isto?" mas "preciso de aceder a isto *de fora da
máquina*?" — e quase sempre a resposta é não: a aplicação fala com a base
de dados pela rede do Docker, que é privada.

## Ver

- `aes/tickets/T027.md` — o incidente completo
- `docker-compose.yml` — a publicação no loopback
- `configuration.php` — o override por ambiente
- `docker/apache.conf` — o `PassEnv`
