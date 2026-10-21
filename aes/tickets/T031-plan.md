---
ticket: T031
phase: plan
status: done
created: 2026-10-21
tier: heavy
verdict: pass
requires:
  - aes/tickets/T028-verify.md
  - aes/tickets/T031.md
produces:
  - aes/tickets/T031-plan.md
blocked_by: ''
---

# T031 — Plan: Sistema de Recuperação de Credenciais

## Reconnaissance Summary

| Fact | Evidence |
|------|----------|
| `tblclients.pwresetkey` (text) + `pwresetexpiry` (int) | real WHMCS 5.3.9 schema — the reset token has a home, **no schema change** |
| `tblcontacts.pwresetkey` + `pwresetexpiry` | the same, for the contact sub-accounts |
| `tbladmins` has **no** reset columns | the admin needs a store that is not the database |
| No SMTP credentials | `configuration.php` has no mail settings; the delivery must degrade |
| The app already has `POST /api/v1/auth/forgot-password` | it returns a fixed message and does nothing — a stub |
| The app already has a `PasswordResetScreen` | it posts to the stub; it needs the token step |
| The admin login page has no "esqueci a senha" | the operator has no self-service path |

## Two-Agent Analysis (heavy tier)

### Critic Phase

```
## Como se fosse uma criança
Perdi a chave da casa. Quero um serviço que me mande uma chave nova pelo
correio. Mas o correio não está ligado — e o carteiro não pode dizer
a toda a gente que a casa existe. E o multibanco da esquina tem uma
caixa de chaves para os clientes, mas a porta da loja do proprietário
não tem.

## Como se fosse um especialista
Three constraints collide: the schema is fixed, the mail is absent, and
the response must not disclose account existence.

[FAILURE MODE 1] — Adding a table (or columns) for the reset tokens
would be the obvious implementation and it **changes the production
schema**. The operator's requirement is compatibility with the *current*
database; a migration is a different project, and the deployed database
does not have the new table.
Mechanism: any new table means the code has a hard dependency on a
migration having been run. The one-off `make db-reset-admin` path is
*exactly* the kind of out-of-band operation this ticket is meant to
remove.

[FAILURE MODE 2] — The client flow has a home: `tblclients.pwresetkey`
and `pwresetexpiry` are the WHMCS columns for precisely this. The admin
flow does not — `tbladmins` has no such columns. Treating the two
identically would either add columns to production or leave the admin
without a flow.
Mechanism: the admin is a single, known operator account. Its recovery
is an *operational* event, not a public feature. A file-based token
store in the container is zero-schema-change, expires, and is auditable.

[FAILURE MODE 3] — Without SMTP, a token that is generated but cannot be
delivered is a broken feature: the user is told "check your email" and
nothing arrives. The response would also be a lie.
Mechanism: the delivery is pluggable. When SMTP is configured, the mail
goes out. When it is not, the token is written to a file the administrator
reads and hands over — and the *response* says so, in development. In
production the response stays generic.

[FAILURE MODE 4] — A reset token that is not single-use and does not
expire is a backdoor that lives in the database.
Mechanism: the token is stored hashed-ish (random 32 bytes), carries an
expiry, and is **cleared on use**. A successful reset invalidates it.

[ASSUMPTION] If false — "recuperação de credenciais" means a *support*
workflow where an operator resets a customer's password on their behalf
(with the customer's consent), not a self-service email flow: the
consequence is that the flow is a different one, and the customer's
email is not the identity proof.
Validation: the operator said "tentei a Linuxkafe-2026 e não validou" —
they are trying to get *into the system themselves*, which is the
**operator** case, not the customer case. Both are therefore delivered.

[ALTERNATIVE FRAMING] Instead of "a password reset feature", frame it
as "one way in, whenever the credential is lost": the CLI tool, the
in-app flow and the token file are three doors to the same room, and each
one works even when the others are broken. That is what the operator
actually needs: **a way in that does not depend on the thing that broke.**

## Porquê? ×5
1. Why not a new table? → The production schema is the WHMCS; a migration
   is a different project.
2. Why use `pwresetkey` for clients? → Because the WHMCS put it there for
   exactly this, and the app should speak the same dialect.
3. Why a file for admins? → Because the admin is one known operator, and
   the recovery is an operational event.
4. Why a pluggable delivery? → Because there is no SMTP, and a feature
   that promises an email it cannot send is worse than none.
5. Why clear the token on use? → Because a reset token that survives its
   own use is a persistent backdoor.

## Implementor Phase

```
## Como se fosse uma criança
Faço um bilhete com um número aleatório grande, guardo o bilhete na
casa do cliente com uma data de validade, e mando o bilhete pelo correio.
Quando o cliente volta com o bilhete, troco a fechadura, apago o
bilhete, e a fechadura nova é forte.

Para o dono da loja, o bilhete fica num cacifo na loja — porque a loja
não tem cacifo de clientes, e não posso partir o esquema.

## Como se fosse um especialista
(D1) `PasswordResetService` — the client flow.

```php
public function requestReset(string $email): bool
{
    $client = $this->clients->findByEmail($email);
    if (!$client) return false;           // the caller never reveals it

    $token = bin2hex(random_bytes(32));
    $this->clients->setResetToken((int)$client['id'], $token, time() + self::TTL);

    $this->deliver($email, $token);

    return true;
}

public function resetWithToken(string $token, string $password): bool
{
    $client = $this->clients->findByResetToken($token);
    if (!$client) return false;
    if ((int)$client['pwresetexpiry'] < time()) return false;

    $this->clients->update((int)$client['id'], [
        'password' => $this->hasher->hash($password),
    ]);
    $this->clients->clearResetToken((int)$client['id']);

    return true;
}
```

The token is 32 random bytes, stored with an expiry, and **cleared on use**.

(D2) `AdminPasswordResetService` — the admin flow, file-backed.

`tbladmins` has no reset columns and the schema must not change, so the
token lives in `storage/reset-tokens/admin/<token>.json` with an expiry.
The store is a small, testable class.

(D3) A pluggable delivery.

```php
interface TokenDelivery {
    public function deliver(string $email, string $token): bool;
}
```
- `SmtpTokenDelivery` — used when `XKHM_SMTP_HOST` is set
- `FileTokenDelivery` — writes the token to `storage/reset-tokens/pending/`
  and says so in the response (development), always writes it

(D4) The API.

```
POST /api/v1/auth/forgot-password          {email}      → sempre a mesma resposta
POST /api/v1/auth/reset-password           {token,password}
POST /api/v1/admin/auth/forgot-password    {username}   → sempre a mesma resposta
POST /api/v1/admin/auth/reset-password     {token,password}
```

(D5) The Flutter screens: `PasswordResetScreen` gains the token step; the
admin login page gains the link.

(D6) The CLI: `make db-reset-tokens` lists the pending tokens; and the
existing `make db-reset-admin` / a new `make db-reset-client`.

## ADDRESSING FAILURE MODE 1 (schema change)
Mitigation: **the client flow uses the WHMCS columns and the admin flow
uses a file.** Nothing is added to the database. The parity test
(`SchemaParityTest`) still passes against the production schema, which is
the proof.

## ADDRESSING FAILURE MODE 2 (asymmetric stores)
Mitigation: two services with the same public contract
(`requestReset` / `resetWithToken`), backed by different stores. The
application code does not know which is which.

## ADDRESSING FAILURE MODE 3 (undeliverable token)
Mitigation: the delivery is pluggable and **the file delivery always
writes the token**, so there is always a way in. The response says where
it is, in development. The operator is never told "check your email" when
no email was sent.

## ADDRESSING FAILURE MODE 4 (reusable token)
Mitigation: the token is cleared on use, it carries an expiry, and it is
32 random bytes. A test asserts that a second use of the same token fails.

## ADDRESSING ASSUMPTION (support vs. self-service)
Resolution: **both are delivered.** The self-service flow is the product
feature; the CLI tools are the operator's door, and they work even if the
whole HTTP stack is broken.

## [SOLUTION PROPOSAL]
Stage 1 — the services and the stores.
Stage 2 — the API endpoints.
Stage 3 — the Flutter screens and the admin link.
Stage 4 — the CLI tools.
Stage 5 — the tests.

## Porquê? ×5
1. Why a TTL? → A reset token is a temporary key; it must expire.
2. Why clear the token? → A token that survives its use is a backdoor.
3. Why always write the file? → There must always be a way in.
4. Why the same generic answer? → Otherwise the endpoint is an account
   oracle.
5. Why keep the CLI? → The operator needs a door that does not depend on
   the thing that broke.

What are we optimizing that we shouldn't be?
We are optimising for "a polished email flow". We should be optimising
for "a way in that always works".
```

## Hostile Analysis

```
ASSUMPTIONS I AM MAKING:
- [KNOWN]    The production schema stays as it is — evidence: the whole
  T028 effort was to conform to it, not to change it.
- [KNOWN]    There is no SMTP — evidence: `configuration.php` has no mail
  settings and the deployment has no mail service.
- [INFERRED] The operator needs a *self-service* path too (their clients
  will lose their passwords).
- [ASSUMED]  The operator can read a file in the container — evidence: they
  run it.

WHAT WAS NOT SPECIFIED:
- The token's lifetime → resolved: 1 hour (WHMCS's own default is 1 day;
  one hour is enough for a password reset and shortens the exposure).
- Whether to notify the client of the reset → resolved: yes, when the mail
  is available; the file delivery carries it to the administrator.

ALTERNATIVES NOT CHOSEN:
- Option A: a new `tblpasswordresets` table → rejected: it changes the
  production schema.
- Option B: reuse `tblclients.pwresetkey` for the admin too → rejected:
  the admin row has no such column.
- Option C: a signed, stateless token (JWT-ish, HMAC) → rejected: it
  needs a signing secret that would have to live somewhere, and it cannot
  be revoked. The stored token can be revoked by clearing it.

RISKS AND SIDE EFFECTS:
- The token file contains a live credential → it is written with 0600, to a
  directory outside the document root, and it is listed by an explicit
  command rather than being browsable.
- The response in development reveals the token → that is the point in
  development; in production the response is generic.

COST OF BEING WRONG: medium — a broken recovery is worse than none, because
it is relied upon.

SCOPE BOUNDARY:
In scope: the services, the API, the screens, the CLI, the tests.
Out of scope: SMTP configuration, schema changes, 2FA.
```

## Technical Approach

**Chosen:** one contract, two stores (the WHMCS columns for the clients, a
file for the admin), a pluggable delivery that always leaves a usable token
behind, and a CLI that is a door of its own.

**What will NOT be implemented:** any schema change; SMTP configuration;
in-app token display in production.

### Affected files

| File | Operation | Description |
|------|-----------|-------------|
| `src/Service/PasswordResetService.php` | modify | The client flow, on the real WHMCS columns |
| `src/Service/AdminPasswordResetService.php` | create | The admin flow, file-backed |
| `src/Service/TokenDelivery.php` | create | The delivery contract |
| `src/Service/SmtpTokenDelivery.php` | create | Used when SMTP is configured |
| `src/Service/FileTokenDelivery.php` | create | Always writes the token locally |
| `src/Repository/ClientRepositoryPDO.php` | modify | The reset-token methods |
| `src/Http/Controller/Api/ApiV1Controller.php` | modify | The four endpoints |
| `public/index.php` | modify | Register the admin endpoints |
| `src/Http/Controller/AdminAuthController.php` | modify | The "esqueci a senha" link |
| `flutter/xkhm_app/lib/screens/password_reset_screen.dart` | modify | The token step |
| `flutter/xkhm_app/lib/screens/admin_password_reset_screen.dart` | create | The admin flow |
| `tools/list_reset_tokens.py` | create | `make db-reset-tokens` |
| `tools/reset_client_password.py` | create | `make db-reset-client` |
| `tests/Integration/PasswordResetServiceTest.php` | create | The flow's rules |
| `tests/Playwright/test_password_reset.py` | create | The end-to-end flow |

## Testing Strategy

- **The rules:** no account disclosure, the token expires, the token is
  single-use, the new password is bcrypt, the token is cleared.
- **The end-to-end:** request → read the token → reset → log in with the
  new password → the old one no longer works.

## Verification Criteria

- [ ] All ten acceptance criteria evidenced
- [ ] The production schema is unchanged (the parity test still passes)
- [ ] `make test`, `make lint`, `make e2e`, `make flutter-test` green

## Estimation

- Complexity: high (> 8h)
- Risk: medium
- Blocking dependencies: no
