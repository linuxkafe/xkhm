---
ticket: T031
phase: verify
status: done
created: 2026-10-21
verdict: pass
requires:
  - aes/tickets/T031-plan.md
---

# T031 — Verify: Sistema de Recuperação de Credenciais

## Gates

| Gate | Command | Result |
|------|---------|--------|
| PHPUnit | `make test` | **103 tests, 210 assertions** — green |
| PHPStan (nível 5) | `make lint` | **0 erros** |
| Flutter | `make flutter-test` | **45 testes** — green |
| Playwright | `make e2e` | **152 testes** — green |
| Tokens | `make tokens-check` | em sincronia, sem cores literais |

## Acceptance criteria

| AC | Critério | Evidência | Estado |
|----|----------|-----------|--------|
| AC1 | O pedido nunca revela se a conta existe | `test_the_answer_never_reveals_whether_the_account_exists`; `test_the_admin_answer_never_reveals_whether_the_account_exists` — a mensagem é **idêntica** nas duas respostas | ✅ |
| AC2 | O token do cliente vive nas colunas WHMCS reais | `test_the_token_is_stored_in_the_whmcs_columns` — `tblclients.pwresetkey` / `pwresetexpiry`; **nenhuma coluna ou tabela nova** | ✅ |
| AC3 | O token expira | `test_an_expired_token_is_refused` — TTL de 1 h; token expirado é apagado e recusado | ✅ |
| AC4 | O token é de uso único | `test_the_token_is_single_use`; `test_the_token_cannot_be_used_twice` (E2E) — a segunda tentativa dá 400 | ✅ |
| AC5 | A nova senha é gravada em bcrypt | `test_the_new_password_is_stored_as_bcrypt` — o hash começa por `$2y$` | ✅ |
| AC6 | Sem SMTP o token é entregue de forma utilizável | `FileTokenDelivery` escreve sempre em `storage/reset-tokens/pending/`; `make db-reset-tokens` lista-o | ✅ |
| AC7 | O admin tem o mesmo fluxo, sem alterar o esquema | `test_the_admin_flow_uses_a_file_and_not_the_database`; `test_the_admin_recovery_flow` (E2E) — o token vive em `storage/reset-tokens/admin/` | ✅ |
| AC8 | A app Flutter tem os ecrãs do fluxo | `password_reset_screen.dart` (dois passos: pedido → código → nova senha); `test_the_application_has_the_recovery_screen` | ✅ |
| AC9 | As ferramentas de linha de comando funcionam | `make db-reset-tokens`, `make db-reset-client EMAIL=… PASSWORD=…`, `make db-reset-admin PASSWORD=…` | ✅ |
| AC10 | Todos os portões verdes | tabela acima | ✅ |

## O esquema de produção não mudou

A evidência não é uma promessa: é o **teste de paridade** (T028), que continua
a passar contra o esquema real.

```
tblclients.pwresetkey    text     ← já existia no WHMCS 5.3.9
tblclients.pwresetexpiry int      ← já existia no WHMCS 5.3.9
```

Nenhuma tabela nova. Nenhuma coluna nova. Nenhuma migração.

`tbladmins` **não** tem colunas de reset — por isso o token do administrador
vive num ficheiro, e não numa coluna inventada.

## O fluxo, verificado a correr

### Cliente

```
POST /api/v1/auth/forgot-password  {"email":"xkhm-reset@example.com"}
  → {"ok":true,"data":{"message":"Se a conta existir, enviámos…",
       "developmentToken":"8e41b7d9…"}}

SELECT pwresetkey, pwresetexpiry FROM tblclients WHERE email=…
  → 8e41b7d91dca5d705956…  /  1792594473      ← as colunas WHMCS

POST /api/v1/auth/reset-password   {"token":"8e41…","password":"NovaSenha2026"}
  → {"ok":true,"data":{"message":"Senha alterada com sucesso."}}

SELECT pwresetkey FROM tblclients …
  → ""                    ← o token foi apagado (uso único)

POST /api/v1/auth/login  {senha nova}   → 200
POST /api/v1/auth/login  {senha antiga} → 401
```

### Administrador

```
POST /api/v1/admin/auth/forgot-password  {"username":"xkhm-reset-admin"}
  → token + ficheiro storage/reset-tokens/admin/<token>.json

POST /api/v1/admin/auth/reset-password   {"token":"0615…","password":"…"}
  → {"ok":true,"data":{"message":"Senha alterada com sucesso."}}

POST /admin/login.php  (com a nova senha) → 302 → /admin
GET  /admin                                  → 200
```

## Defeitos encontrados e corrigidos durante a verificação

| # | Defeito | Causa | Correcção |
|---|---------|-------|-----------|
| 1 | O contentor servia a versão antiga do código | `docker compose up -d` **não reconstrói** a imagem | `docker compose build --no-cache` antes de `up` — e o ficheiro do token só apareceu depois disso |
| 2 | `Undefined variable $passwordResetService` | os serviços de recuperação eram criados **depois** do controlador da API | o bloco de recuperação passou a ser criado **antes** dos controladores que o usam |
| 3 | `Undefined variable $adminPasswordResetService` | o mesmo, no controlador do admin | o controlador do admin passou a ser criado depois do serviço |
| 4 | 500 no reset do admin: `SQLSTATE[HY093] Invalid parameter number` | `:hash` usado **duas vezes** — com `ATTR_EMULATE_PREPARES => false` o PDO não reutiliza parâmetros nomeados | dois placeholders distintos (`:hash` e `:hash2`) |
| 5 | O ficheiro do token não era escrito | o directório montado é do host (uid 1004, modo 0700) e o PHP corre como `www-data` | directório 0777; ficheiros 0644 (o token expira em 1 h) |
| 6 | 500 em `/admin/logout.php` | a reescrita do `AdminAuthController` **esqueceu-se** do método `logout()` | o método foi reposto; o logout responde 302 e `/admin` volta a pedir login |
| 7 | 29 testes E2E falharam | os testes de recuperação **mudavam a senha** das contas partilhadas | contas reservadas à recuperação (`xkhm-reset@example.com`, `xkhm-reset-admin`) |

Cada um destes defeitos era um **falso positivo do portão** — a suíte
passava enquanto o fluxo real não funcionava. É exactamente o que a fase de
verificação existe para apanhar.

## Nota de segurança

O `developmentToken` só é devolvido quando `APP_ENV=development`. Em
produção a resposta é a mesma para toda a gente — a que existe e a que não
existe — e o token sai por SMTP (quando configurado) ou fica no ficheiro
para o administrador entregar.

`make db-reset-tokens` é a ferramenta de leitura; o token **expira em uma
hora** e é de uso único.
