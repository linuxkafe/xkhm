---
ticket: T037
phase: verify
status: done
created: 2026-10-21
verdict: pass
requires:
  - aes/tickets/T037-plan.md
---

# T037 — Verify: MFA para dispositivos novos, sem alterar a base de dados

## Gates

| Gate | Command | Result |
|------|---------|--------|
| PHPUnit | `make test` | **151 tests, 370 assertions** — green |
| PHPStan (nível 5) | `make lint` | **0 erros** |
| Flutter | `make flutter-test` | **49 testes** — green |
| Playwright | `make e2e` | **209 testes** — green (202 + 7 do MFA) |
| Tokens | `make tokens-check` | em sincronia |

## Acceptance criteria

| AC | Critério | Evidência | Estado |
|----|----------|-----------|--------|
| AC1 | O segredo vive em `authdata`, e **nenhuma** coluna ou tabela é criada | `testTheSecretIsStoredInAnExistingColumn`; e o esquema real: `information_schema` não mudou | ✅ |
| AC2 | O cliente pode inscrever-se (QR + confirmação) | `test_the_enrolment_produces_a_secret_and_a_uri`; `testTheEnrolmentIsConfirmedWithACode`; `test_the_security_screen_exists` | ✅ |
| AC3 | Um login de um **dispositivo novo** exige o código | `test_a_new_device_is_asked_for_the_code` — `mfaRequired: true` | ✅ |
| AC4 | Um login de um **dispositivo conhecido** não o exige | `test_a_known_device_is_not_asked_for_the_code` — o segundo login não pede nada | ✅ |
| AC5 | O cookie de confiança é assinado e expira | `testATrustedDeviceIsRecognised`, `testAnExpiredTrustIsRefused`, `testATamperedTrustIsRefused`, `test_a_tampered_trust_cookie_is_refused` | ✅ |
| AC6 | O desafio é assinado e de vida curta | `testTheChallengeNamesTheClient`, `testAnInvalidChallengeIsRefused`, `test_an_expired_challenge_is_refused`; cinco minutos | ✅ |
| AC7 | A app tem um ecrã de **Segurança** | `test_the_security_screen_exists` — a secção está no menu, com o QR | ✅ |
| AC8 | O `authdata` de outro não é sobrescrito | `testTheEnrolmentRefusesToOverwriteForeignData` — recusa, e o dado fica **exatamente** como estava | ✅ |
| AC9 | Todos os portões verdes | tabela acima | ✅ |

## A restrição, cumprida

**Nenhuma coluna nova. Nenhuma tabela nova.** O esquema de produção é
o mesmo de antes:

```
tblclients.authdata     text     ← já existia; é onde o WHMCS 6/7 guarda
tblclients.authmodule   text        o segredo do 2FA
```

E, ao contrário do que o `SchemaMap` dizia, a coluna deixou de ser
**sintetizada** como vazia: ela é real, tem conteúdo, e a aplicação
passa a ler e a escrever nela.

A prova de que nada mudou:

```sql
SELECT COUNT(*) FROM information_schema.TABLES
 WHERE TABLE_SCHEMA = 'linuxkonect';    -- 94, como antes
```

E o segredo de um cliente encriptado vive lá:

```json
{"xkhm_mfa": {"secret": "O4U7CR3U3RSVRADSLGDNHUF6AKB4IK3O",
              "enabled": true,
              "created": "2026-10-21T21:49:00+00:00"}}
```

## O fluxo, verificado a correr

```
1. POST /api/v1/client/security/enrol
   → {"secret": "O4U7…", "uri": "otpauth://totp/LINUXKAFE:xkhm-test%40example.com?…"}

2. POST /api/v1/client/security/confirm  {"code": "692665"}
   → {"enrolled": true}

3. POST /api/v1/auth/login  {…, "deviceId": "disp-NOVO"}
   → {"mfaRequired": true, "challenge": "84|<nonce>|<expiry>.<assinatura>"}
   → NÃO há sessão

4. POST /api/v1/auth/mfa  {challenge, code, deviceId}
   → a sessão abre, e o cookie XKHM_TRUSTED_DEVICE é emitido

5. POST /api/v1/auth/login  {…, "deviceId": "disp-NOVO"}  (com o cookie)
   → {"mfaRequired": null}   → a sessão abre

6. POST /api/v1/auth/login  {…, "deviceId": "disp-OUTRO"}
   → {"mfaRequired": true}   → o cookie não é de outro dispositivo
```

E os casos negativos:

| Caso | Resposta |
|------|----------|
| Código errado | `401` — «Código inválido.» |
| Desafio adulterado | `401` |
| Desafio expirado | `401` |
| Cookie adulterado | o pedido é de novo |
| Cookie de outro dispositivo | o pedido é de novo |

## O TOTP, contra o RFC

O TOTP é verificado contra os **vectores de teste do RFC 6238** (SHA-1,
o segredo `12345678901234567890`), e não contra a própria
implementação:

```
T=59          esperado=94287082  obtido=94287082  OK
T=1111111109  esperado=07081804  obtido=07081804  OK
T=1111111111  esperado=14050471  obtido=14050471  OK
T=1234567890  esperado=89005924  obtido=89005924  OK
T=2000000000  esperado=69279037  obtido=69279037  OK
T=20000000000 esperado=65353130  obtido=65353130  OK
```

## A assinatura

- A chave é **derivada** do segredo partilhado com o WHMCS:
  `hash_hmac('sha256', 'xkhm.mfa.v1', $ccEncryptionHash)` — o mesmo
  segredo, um propósito diferente.
- A comparação é em **tempo constante** (`hash_equals`).
- O token carrega o instante em que foi emitido **e** o em que expira:
  a validade não depende do relógio do servidor no momento da
  verificação, e a idade máxima é medida a partir da emissão.

## Defeitos encontrados durante a verificação

| # | Defeito | Causa | Correcção |
|---|---------|-------|-----------|
| 1 | A inscrição **nunca** escrevia o segredo | o `SchemaMap` **sintetizava** `authdata` como vazio — e o `toReal` descartava o valor | a coluna deixou de ser sintetizada: é real, e é lida e escrita |
| 2 | O `authdata` ficava `{}` ao desativar | um documento sem a nossa chave é «dados de outra pessoa» — e bloqueava a inscrição **para sempre** | desativar escreve a coluna **vazia**; e um documento vazio é reconhecido como tal |
| 3 | O token assinado confundia a **validade** com a **idade** | o payload só tinha a expiração, e a idade era calculada a partir dela | o payload tem emissão **e** expiração |
| 4 | O TOTP parecia errado | os vectores do RFC são de **8 dígitos** e a app usa 6 — os 6 últimos batiam certo | o número de dígitos é configurável, e o teste usa 8 para comparar com o RFC |
| 5 | O QR falhava a verificação de cores | o `Colors.white` é um literal | o verificador ganhou uma **excepção explícita** (`tokens-check: allow`), para o caso em que o literal é uma **exigência** e não uma decisão de marca |

O primeiro é o mais importante: **a coluna existia, o esquema era o
certo, e o `SchemaMap` dizia o contrário.** A síntese de `authdata` foi
uma decisão de conveniência tomada noutro ticket — e o MFA pagou
por ela.
