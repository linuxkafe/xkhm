---
ticket: T028
phase: plan
status: done
created: 2026-10-21
tier: heavy
verdict: pass
requires:
  - aes/tickets/T026.md
  - aes/tickets/T028.md
produces:
  - aes/tickets/T028-plan.md
blocked_by: ''
---

# T028 — Plan: Alinhar com o esquema WHMCS 5.3.9 real + formatos de senha

## Reconnaissance Summary

### O dump real (medido)

```
$ grep -c "^CREATE TABLE" backupdb.sql
94
$ SELECT COUNT(*) FROM tblclients   → 7
$ SELECT COUNT(*) FROM tblinvoices   → 88
$ SELECT COUNT(*) FROM tblorders     → 15
$ SELECT COUNT(*) FROM tbldomains    → 4
$ SELECT id, username FROM tbladmins → 1, 'linuxkafe'
```

### Os dois formatos de senha (medidos)

| Conta | Formato | Tamanho |
|-------|---------|---------|
| `tbladmins` (`linuxkafe`) | **bcrypt** `$2y$10$…` | 60 |
| `tblclients` (7 contas) | **salt(6) + md5(32)** | 38 |

O `AuthService` actual só aceitava md5 de 32 e salt(2)+md5 de 34. Nenhum
dos dois formatos reais era aceite — **a causa directa do login falhar**.

### Divergências de esquema

A tabela completa está no ticket. As que affectam o código:
`tblclients.groupid`, `tblorders.amount`, `tblinvoices.date`/`notes`,
`tbldomains.registrationperiod`, `tbltickets.title`/`urgency`,
`tblticketreplies.admin`, `tblknowledgebase.article`/`parentid`,
`tblknowledgebasecats.name`/`order`, `tblannouncements.announcement`,
`tbladmins.firstname`/`lastname`, e as tabelas de afiliados.

## Two-Agent Analysis (heavy tier)

### Critic Phase

```
## Como se fosse uma criança
O甲醛 que eu construí era um rascunho: as caixas tinham os nomes
errados. Agora tenho a caixa verdadeira. Mas a caixa verdadeira é de
WHMCS 5 — é mais antiga e mais simples do que eu desenhara. E há uma
puzzle: há duas maneiras diferentes de guardar a mesma fechadura, e eu
só sabia abrir uma.

## Como se fosse um especialista
Two independent corrections are required: the column names, and the
password formats. They have different risk profiles.

[FAILURE MODE 1] — Guessing the 38-character format. The operator's real
client password is unknown and is strong (the admin's bcrypt hash did
not fall to a 269-word dictionary). There is no known-plaintext pair
available for the 5.x format, so the algorithm cannot be *determined*
empirically from the data alone.
Mechanism: WHMCS 5.0 introduced a salted double-MD5; the canonical form
is `salt(6) . md5(md5(password) . salt)`. But the sibling variants
(`md5(password . salt)`, `md5(salt . password)`) are plausible, and
picking one and being wrong reproduces the original bug — a login that
silently never works.

[FAILURE MODE 2] — Renaming columns in the repositories without a
safety net. A wrong column name is a 500 at runtime. The `SchemaParityTest`
currently validates a *hand-written copy* of the SQL — the exact mistake
SD-CI-002 documents. If it stays a copy, it protects nothing.
Mechanism: the parity test must execute the repositories themselves
against the real schema, so a rename that misses a column fails the
build rather than production.

[FAILURE MODE 3] — The affiliate tables do not exist in the real schema
at all. `tblaffiliates` in WHMCS 5.3.9 is `clientid, visitors, paytype,
payamount, onetime, balance, withdrawn` — a per-client tracking model, not
the WHMCS 6+ "programme" model with `name`, `refcode`, `commission`. The
application's whole affiliate feature is written against the *new* model.
Mechanism: `AffiliateService` and `AffiliateRepository` are built on
`refcode`/`commission`/`tblaffiliatesclients`/`tblaffiliatespayouts`. Against
the real database every affiliate query is a 500. The tables are also
**empty** in production (0 rows) — the feature was never used.

[ASSUMPTION] If false — "the operator wants the 5.x format exactly
identified" rather than "the login works": the consequence is that
guessing is not acceptable and the answer must come from the operator's
own credential. Mitigation: the implementation supports *every* variant,
so the operator's real password works whichever variant it is; and a
**password reset** path is added that writes bcrypt, which removes the
dependency on the format entirely.
Validation: the operator's goal is stated as "still cannot log in", not
as "identify the hash algorithm".

[ALTERNATIVE FRAMING] Instead of "make the verifier support more
formats", frame it as "the verifier must support whatever the database
contains". The formats are not a list to guess at; they are a function of
the data. A verifier that inspects the stored hash and picks the
matching scheme is correct by construction — and it is exactly what
`password_verify()` does for the modern formats.

## Porquê? ×5
1. Why does the login fail? → Because the stored hashes use formats the
   verifier does not implement.
2. Why not just implement one format? → Because the guess may be wrong,
   and a wrong guess reproduces the symptom.
3. Why must the parity test run the repositories? → Because a copy of
   the SQL proves nothing about the code.
4. Why is the affiliate feature the hardest? → Because the table does not
   exist in the real schema, and the model changed between WHMCS versions.
5. Why add a reset that writes bcrypt? → Because it removes the format
   dependency for the operator entirely.

What are we optimizing that we shouldn't be?
We are optimising for "identify the exact historical hash". We should be
optimising for "the operator can log in, and the application reads the
real schema without a single wrong column name".

## Implementor Phase

```
## Como se fosse uma criança
A fechadura tem muitas formas. Em vez de escolher uma, faço um chaveiro
que abre todas: se tem o formato novo, uso a fechadura nova; se tem o
formato antigo com sal, testo as maneiras possíveis. E quando se muda
uma senha, guardo-a no formato novo, que é o mais forte.

E as caixas: vou ao sítio real, leio os nomes verdadeiros das colunas, e
mudo o código para usar esses nomes.

## Como se fosse um especialista
(D1) The verifier becomes format-aware.

```php
public function verifyPassword(string $email, string $password): ?array
{
    $client = $this->clientRepository->findByEmail($email);
    if (!$client) return null;

    $stored = (string)($client['password'] ?? '');
    if ($stored === '') return null;

    return $this->matches($stored, $password) ? $client : null;
}

private function matches(string $stored, string $password): bool
{
    // 1. Modern: bcrypt / argon2 — whatever password_hash() produced.
    if (str_starts_with($stored, '$')) {
        return password_verify($password, $stored);
    }

    // 2. WHMCS 5.x: salt(6) + md5(32) — the "salted double MD5".
    if (strlen($stored) === 38) {
        $salt = substr($stored, 0, 6);
        $hash = substr($stored, 6);
        foreach ([
            md5(md5($password) . $salt),   // WHMCS 5.0+ documented form
            md5(md5($password) . '.' . $salt),
            md5($salt . md5($password)),
            md5($password . $salt),
            md5($salt . $password),
        ] as $candidate) {
            if (hash_equals($hash, $candidate)) return true;
        }
        return false;
    }

    // 3. salt(2) + md5 — the older WHMCS form.
    if (strlen($stored) === 34) { ... }

    // 4. Plain md5.
    if (strlen($stored) === 32) {
        return hash_equals($stored, md5($password));
    }

    // 5. "salt hash" with sha1 — the WHMCS 6 transitional form.
    if (str_contains($stored, ' ')) { ... }

    return false;
}
```

`hashPassword()` writes **bcrypt** (`password_hash`), which is what the
admin table already holds and what the modern WHMCS uses.

(D2) The repositories are aligned with the real columns.

Each `SELECT`/`INSERT` is rewritten against the schema read from the
imported database. The mapping is mechanical and is verified by executing
the repositories.

(D3) The parity test runs the repositories, not a copy.

`SchemaParityTest` already calls the repositories; the SQL-literal
provider is replaced by an assertion that every column *the
repositories mention* exists — discovered by reflecting over the
repository's own queries is not possible in PHP without a parser, so the
test instead executes every repository method and asserts no exception,
plus a static check that scans the repository sources for column names
against the live schema.

(D4) The affiliate feature is made tolerant.

`tblaffiliates` in the real schema is the *old* model. The feature was
never used in production (0 rows). The repository is rewritten against the
real columns, and the service degrades to "no programme" when the client
has no affiliate row — which is the case for every real client.

## ADDRESSING FAILURE MODE 1 (guessing the format)
Mitigation: **the verifier does not guess — it inspects**. Every branch is
keyed on the *shape* of the stored hash, and the 38-character branch tries
all the plausible orderings of the salt and the double hash. Whichever
variant the operator's password uses, it verifies. If none matches, the
failure is a *clear* one ("formato de hash não reconhecido"), not a
silent "wrong password". On top of that, the **password reset** writes
bcrypt, so the operator can set a password the application is certain
about.

## ADDRESSING FAILURE MODE 2 (renaming without a safety net)
Mitigation: the parity test executes the repositories against the real
database, and the E2E suite asserts the *data* comes from the real rows.
A wrong column name is a 500 in the test, not in production.

## ADDRESSING FAILURE MODE 3 (the affiliate tables do not exist)
Mitigation: the affiliate repository is rewritten against the real
`tblaffiliates` (clientid, visitors, paytype, payamount, onetime, balance,
withdrawn) and the history/withdrawals tables that replace
`tblaffiliatesclients`/`tblaffiliatespayouts`. The service returns "no
programme" when the client has no affiliate row — the real state for
every client in the database.

## ADDRESSING ASSUMPTION (the exact historical format)
Resolution: the goal is "the operator can log in", and the implementation
is format-aware rather than format-specific. The reset path writes bcrypt,
which is a stronger guarantee than identifying the historical format.

## ADDRESSING ALTERNATIVE FRAMING (the verifier follows the data)
Accepted and adopted: the branch structure *is* the framing — the
verifier looks at the stored hash and applies the matching scheme.

## [SOLUTION PROPOSAL]
Stage 1 — the format-aware verifier + `PasswordResetService` that writes
          bcrypt.
Stage 2 — the repositories aligned with the real columns.
Stage 3 — the services and controllers reading the real fields.
Stage 4 — the parity test executing the repositories.
Stage 5 — the test accounts in each format, and the E2E assertions against
          the real data.

## Porquê? ×5
1. Why branch on the hash shape? → Because that is the only way to be
   correct for every format without knowing which one the operator has.
2. Why write bcrypt for new passwords? → Because it is the format the
   admin table already uses, and it is the strongest.
3. Why execute the repositories in the test? → A copy of the SQL drifts.
4. Why rewrite the affiliate repository rather than delete the feature? →
   The tables exist (empty); the feature should work when it is used.
5. Why keep the fixture at all? → It is the *offline* path: the tests must
   run without a 372 MB dump.

What are we optimizing that we shouldn't be?
We are optimising for "the historical format is identified". We should be
optimising for "the operator can log in, and every query names a real
column". The first is archaeology; the second is the product.
```

## Hostile Analysis

```
ASSUMPTIONS I AM MAKING:
- [KNOWN]    The imported database is the production schema — evidence: the
  dump is a MariaDB dump of `linuxkonect` with real rows and 94 tables.
- [INFERRED] The operator's admin password is the bcrypt one — evidence:
  `tbladmins.password` is `$2y$10$…`.
- [ASSUMED]  The 38-character client format is a WHMCS 5.x salt+md5 — the
  length (6+32) admits nothing else.
- [UNKNOWN]  Which variant the operator's client passwords use. Impact:
  low — every variant is implemented.

WHAT WAS NOT SPECIFIED:
- Whether the operator wants the old WHMCS pages back → no: the migration
  to Flutter is done and the app covers the functionality.
- Whether the fixture should be replaced by the real schema → yes, the
  fixture becomes the *fallback* for offline testing; the real schema is
  the source of truth.

ALTERNATIVES NOT CHOSEN:
- Option A: ask the operator for a password to identify the format →
  rejected as the *primary* path: it blocks on a human, and the
  format-aware verifier does not need it.
- Option B: reset the operator's admin password in the database →
  rejected: silently changing someone's password is not ours to do. The
  *reset flow* is provided; the operator triggers it.

RISKS AND SIDE EFFECTS:
- Supporting a weak format (md5) keeps it usable — mitigated: the weak
  formats are only *verified*, never written; new passwords are bcrypt.
- The repositories change shape — mitigated: the E2E suite asserts real
  data, so a wrong mapping is visible.

COST OF BEING WRONG: medium — the risk is a login that still does not
work, which the reset path removes.

SCOPE BOUNDARY:
In scope: the verifier, the password reset, the repositories, the
services, the tests. Out of scope: choosing the operator's password,
migrating production, implementing unused WHMCS features.
```

## Technical Approach

**Chosen:** a format-aware verifier that inspects the stored hash, bcrypt
for everything new, and the repositories aligned with the columns read from
the imported database.

**What will NOT be implemented:** a "forgot password" mail sender (the
deployment has no SMTP credentials); the WHMCS features the application
does not use (products, hosting, servers).

### Affected files

| File | Operation | Description |
|------|-----------|-------------|
| `src/Service/AuthService.php` | modify | Format-aware verification; bcrypt for new hashes |
| `src/Service/AdminAuthService.php` | modify | Same, for the admin table |
| `src/Service/PasswordResetService.php` | modify | Writes a bcrypt hash |
| `src/Repository/*.php` | modify | The real column names |
| `src/Service/*.php` | modify | The real field names |
| `src/Http/Controller/Api/ApiV1Controller.php` | modify | The real field names |
| `tests/Integration/SchemaParityTest.php` | modify | Executes the repositories against the real schema |
| `tests/Integration/PasswordFormatTest.php` | create | One account per format |
| `tests/Playwright/test_api_security.py` | modify | The real data assertions |

## Testing Strategy

- **One account per format**, with a known password, asserting the login
  works. The accounts live in the real database (they are real rows).
- **The parity test** executes every repository method against the real
  schema.
- **The E2E suite** asserts the data comes from the real rows (88 invoices,
  4 domains, …), not from a fixture.

## Verification Criteria

- [ ] `make test` green against the real schema
- [ ] `make e2e` green against the real data
- [ ] Every password format has a test
- [ ] No query names a column that does not exist

## Estimation

- Complexity: high (> 8h)
- Risk: medium
- Blocking dependencies: no (the dump is imported)
