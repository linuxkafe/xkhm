---
ticket: T032
phase: verify
status: done
created: 2026-10-21
verdict: pass
requires:
  - aes/tickets/T032-plan.md
---

# T032 — Verify: Rotação da senha do MySQL + entrega de correio

## Gates

| Gate | Command | Result |
|------|---------|--------|
| PHPUnit | `make test` | **112 tests, 228 assertions** — green |
| PHPStan (nível 5) | `make lint` | **0 erros** |
| Flutter | `make flutter-test` | **45 testes** — green |
| Playwright | `make e2e` | **157 testes** — green (152 + 5 do correio) |
| Tokens | `make tokens-check` | em sincronia |

## Acceptance criteria

| AC | Critério | Evidência | Estado |
|----|----------|-----------|--------|
| AC1 | A senha de produção não aparece em nenhum ficheiro versionado | `git grep "qm9W8amh5XV9A4Dr"` → **nenhum**; o mesmo para o hash de encriptação | ✅ |
| AC2 | O healthcheck usa a senha **local** | `docker-compose.yml:76-84` — `MYSQL_PWD="$$MYSQL_PASSWORD"`, lido do ambiente | ✅ |
| AC3 | A senha do MySQL local foi **rodada** e é forte | `linuxkonect` e `root` rodados; 32 caracteres de `[A-Za-z0-9-_.@+=~]` | ✅ |
| AC4 | As credenciais vivem num `.env` não versionado, 0600 | `-rw------- 1 seyon seyon .env` | ✅ |
| AC5 | Nenhum default de senha no repositório | `${XKHM_DB_PASSWORD:?defina XKHM_DB_PASSWORD no .env (make db-env)}` | ✅ |
| AC6 | A ferramenta de rotação verifica o resultado | `rotate_db_password.py`: a antiga falha, a nova funciona; recusa a senha igual | ✅ |
| AC7 | O SMTP sem autenticação funciona | `buildCommand()` não inclui `--user` sem credenciais; entrega real pelo Postfix | ✅ |
| AC8 | O ficheiro do token é **sempre** escrito | `TokenDispatcher` escreve a fila **antes** de tentar o correio | ✅ |
| AC9 | O correio do host entrega o token e apaga o ficheiro | entregue em ~5 s pelo `crontab`; a caixa de correio cresceu 2045 → 3002 bytes | ✅ |
| AC10 | A suíte continua verde | tabela acima | ✅ |

## A cadeia do correio, verificada por teste

`tests/Playwright/test_mail_delivery.py` ergue um **servidor SMTP falso**
e conduz a cadeia inteira:

```
test_the_application_writes_the_queue           a aplicação escreve a fila
test_the_mailer_delivers_and_clears_the_queue   o mailer entrega e limpa
test_the_mailer_refuses_the_reserved_domains    os domínios reservados não saem
test_a_used_token_leaves_the_queue              um token usado sai da fila
test_a_real_address_is_not_reserved             a regra não apanha um cliente real
```

O teste **pausa o `crontab`** durante a suite: o correio automático e o
teste disputa a mesma fila, e o `cron` podia entregar o token ao Postfix
real entre a aplicação o escrever e o teste o ler.

## A rotação, verificada a correr

```
$ python3 tools/rotate_db_password.py --host 127.0.0.1 --port 13306 \
      --user linuxkonect --password <a-antiga> \
      --root-user root --root-password <a-antiga-do-root> --apply

  servidor:  alcançável
  a rodar...
  a senha antiga ainda funciona? não
  a senha nova funciona?         sim

  [OK]    senha rodada e verificada.
```

A rotação **não é uma promessa**: a ferramenta liga-se duas vezes, e a
senha antiga tem de **falhar** e a nova tem de **funcionar**.

## O healthcheck, antes e depois

O healthcheck antigo era um **no-op**:

```
$ mysqladmin ping -h 127.0.0.1 -u linuxkonect -pSENHA_ERRADA
  exit=0        ← verde, com a senha errada
```

O `mysqladmin ping` responde "alive" quando o servidor responde — e o
servidor responde *antes* de autenticar. O contentor esteve "healthy"
com uma senha errada durante meses.

O novo autentica de verdade:

```
$ MYSQL_PWD="SENHA_ERRADA" mysql -h 127.0.0.1 -u linuxkonect -e "SELECT 1"
  exit=1        ← vermelho, como deve ser
$ MYSQL_PWD="$MYSQL_PASSWORD" mysql -h 127.0.0.1 -u linuxkonect -e "SELECT 1"
  exit=0
```

E a senha não está na linha de comando (via `MYSQL_PWD`), para não
aparecer no `ps` de toda a gente.

## A entrega de correio, verificada a correr

A cadeia inteira, com o servidor SMTP de teste a registar o que recebeu:

```
1. a aplicação escreve a fila
   storage/reset-tokens/pending/admin-30a8d5512a4d8a72.txt
     purpose: admin
     recipient: xkhm-test@localhost      ← o EMAIL do admin
     account: xkhm-reset-admin           ← a conta
     token: 30a8d5512a4d8a7207cae8a6b32a83904c30fd8946cd2a476aa964739a49af4a
     created: 2026-10-21T15:44:40+00:00

2. o correio do host entrega
   [OK]    xkhm-test@localhost

3. a fila fica vazia
   0 ficheiros pendentes
```

E a mensagem entregue:

```
Subject: LINUXKAFE — redefinicao da senha de administrador
From: safedata@linuxkafe.com
To: xkhm-test@localhost

Ola,

Foi pedido um pedido de redefinicao de senha para a conta "xkhm-reset-admin".

Codigo: 30a8d5512a4d8a7207cae8a6b32a83904c30fd8946cd2a476aa964739a49af4a

O pedido e valido durante uma hora e so pode ser usado uma vez.
Se nao foi voce, ignore esta mensagem.
```

### Contra o Postfix real

```
$ python3 tools/mail_pending_tokens.py
  [OK]    root@localhost
  1 entregue(s), 0 com falha.

  mailbox antes: 2045 bytes
  mailbox depois: 3002 bytes
```

### Pelo `crontab`, sem intervenção

Deixado um token pendente e à espera:

```
  o cron entregou apos ~5s
  mailbox depois: 3002 bytes
  0 ficheiros pendentes
```

### Quando a entrega falha

Nada se perde: o ficheiro vai para `failed/` **com o motivo**.

```
  [ERRO] xkhm-test@localhost: Connection unexpectedly closed: timed out
  0 entregue(s), 1 com falha.

  storage/reset-tokens/failed/admin-30a8d5512a4d8a72.txt
    ...
    failed: Connection unexpectedly closed: timed out
```

## O que **não** foi possível daqui, e porquê

A senha do MySQL de **produção** (`10.0.3.1`) **não** foi rodada: o
servidor não é alcançável a partir desta máquina.

```
$ ip route get 10.0.3.1
10.0.3.1 via 10.0.0.1 dev eno1 src 10.0.0.111      ← a rota existe

$ (cat < /dev/null > /dev/tcp/10.0.3.1/3306)      ← a porta não responde
$ ping -c1 10.0.3.1                                ← nem responde a ping
```

A ferramenta está escrita, testada e pronta. Onde o servidor é
alcançável:

```bash
python3 tools/rotate_db_password.py \
  --host 10.0.3.1 --port 3306 --user linuxkonect \
  --password <a-senha-atual> --apply
```

E é preciso decidir se o **histórico do git** é reescrito. A rotação
torna a senha velha inútil, o que neutraliza a exposição; reescrever a
história é uma operação destrutiva (afecta todos os clones) e por isso
é uma decisão do operador, não uma decisão automática.

## Dívidas que ficam registadas

| Dívida | Porquê |
|--------|--------|
| O hash de encriptação do WHMCS (`CC_ENCRYPTION_HASH`) saiu do repositório, mas **não** pode ser rodado | É um segredo **partilhado** com o WHMCS; rodá-lo tornaria ilegíveis os dados encriptados na base. Precisa de uma migração. |
| O token do admin é entregue ao `email` de `tbladmins` | O `linuxkafe` tem `mail@linuxkafe.com`. Se esse endereço não for alcançável, o token fica no ficheiro para entrega manual. |
| O correio depende do `crontab` do utilizador | O contentor não tem caminho para o host. Um `crontab` é a forma mais simples de ter um processo no host sem `root`. |
