---
ticket: T026
phase: plan
status: done
created: 2026-10-21
tier: standard
verdict: pass
requires:
  - aes/tickets/T023-verify.md
  - aes/tickets/T026.md
produces:
  - aes/tickets/T026-plan.md
blocked_by: ''
---

# T026 — Plan: Importação dos Dados de Produção + Verificação

## Reconnaissance Summary

| Fact | Evidence |
|------|----------|
| The production DB is unreachable | `ip route get 10.0.3.1` → `via 10.0.0.1` (the default gateway); no route for `10.0.3.0/24`; traceroute leaves via `192.168.1.1` into public IPs; `ping` → 100% loss |
| The local DB is a fixture | `docker/init.sql` creates 16 tables with sample rows; the app reads `linuxkonect` from the `mysql` service |
| The `whmcs` database of the other container is empty | `SHOW TABLES` on `whmcs-mysql` → no tables |
| There is no dump anywhere | `find /home/seyon/dev -name '*.sql'` → only `xkhm/docker/init.sql` and an unrelated `pHantasma` schema |
| The blocker was already known | `whmcs/aes/tickets/T002-dbfunctions.md`: "live DB `10.0.3.1:3306` unreachable from build host" |
| The local schema is a subset | 16 tables vs the ~80 of WHMCS 5.3.9 |
| The repositories only use 16 tables | every `SELECT`/`INSERT` in `src/Repository/` targets the 16 |

### Consequence for the design
A `mysqldump` output **carries its own `CREATE TABLE` statements**. The import
therefore needs no knowledge of the WHMCS schema at all — which is exactly
what we want, because *inventing* the schema is the mistake SD-CI-002 warns
about. The local `init.sql` stays as a development fixture, explicitly
labelled as such.

## Hostile Analysis

```
ASSUMPTIONS I AM MAKING:
- [KNOWN]    The production schema and data are the target, not a replica of
  them — the requirement is "compatible with the current database".
- [INFERRED] The operator can reach 10.0.3.1 from some machine (their desk,
  the server itself) — the DB is in production use.
- [ASSUMED]  A `mysqldump` is the transfer format — impact if false: an
  alternative (a CSV export, a replica) would need a different loader; the
  loader is a thin layer over `mysql`, so adapting it is cheap.
- [UNKNOWN]  Whether the operator can add a route on this machine. Impact:
  medium — if they can, the import is direct; if not, the dump path is used.

WHAT WAS NOT SPECIFIED:
- Who runs the export → resolved: the tool is provided and documented; the
  operator runs it wherever the DB is reachable.
- Whether to import into the same container → resolved: yes, `linuxkonect` in
  the local MySQL, so the application is unchanged.

ALTERNATIVES NOT CHOSEN:
- Option A: hand-write the full WHMCS 5.3.9 schema into `init.sql` —
  rejected: ~80 tables of invented columns; SD-CI-002 is exactly the story of
  a hand-written fixture asserting against a schema that does not exist.
- Option B: make the application connect directly to 10.0.3.1 — rejected: it
  is unreachable; and the local stack would be untestable.
- Option C: replicate the production DB with MySQL replication — rejected: it
  still needs reachability, and it is much heavier than a dump.

RISKS AND SIDE EFFECTS:
- Importing over the development fixture destroys the sample data → mitigated:
  the tool takes a backup of the local database first, and the fixture is
  re-seedable with one command.
- A dump from a production database contains **personal data** (names, e-mails,
  addresses) → the tool must not print row contents, and the artifacts must
  be gitignored. This is a real privacy concern and the tool says so.
- The imported schema may differ from what the repositories expect → that is
  precisely what the verification step is for; the parity test then names the
  divergence instead of hiding it.

COST OF BEING WRONG: low — the tool is additive and does not touch the
application.

SCOPE BOUNDARY:
In scope: the connectivity diagnosis, the export script, the import tool, the
post-import verification, the labelling of the fixture.
Out of scope: the production database itself, the front-end (T025).
```

## Technical Approach

**Chosen:** three tools that answer the operator's question in order —
*why can't I log in?* → *how do I get the data?* → *did it work?*

### 1. `make db-check` — the diagnosis

Answers three questions and prints the next action for each:

```
1. Is the production database reachable from here?
   → no: no route for 10.0.3.0/24 (this machine is on 10.0.0.0/24)
2. Is there a dump available locally?
   → no: no .sql dump found
3. Does the local database have the expected schema?
   → yes: 16 tables (the development fixture)
```

Each failure prints the **concrete remedy**.

### 2. `tools/export_prod_db.sh` — the export

To be run on a machine that can reach the DB:

```bash
mysqldump -h 10.0.3.1 -u linuxkonect -p'...' \
  --single-transaction --routines --triggers --events \
  --default-character-set=utf8mb4 \
  linuxkonect > linuxkonect-YYYYmmdd.sql
```

`--single-transaction` gives a consistent snapshot without locking the
production database. `--default-character-set=utf8mb4` — the lesson from the
latin1 incident (SD-CI-002) applied at the source.

The script refuses to write the password into the file and warns about
personal data.

### 3. `make db-import FILE=<dump>` — the import

1. Backs up the current local database (mysqldump → `docker/backups/`).
2. Applies the dump with `mysql`.
3. Runs the schema parity test against the **imported** database.
4. Prints a report: tables imported, rows per table, and any divergence from
   what the repositories expect.

The parity test already exists (`SchemaParityTest`); it becomes the
verification step, run against real data instead of the fixture.

### 4. Labelling the fixture

`docker/init.sql` gains a header that says, in plain words, that it is a
**development fixture** — not the production data — and the seed password is
documented as such. `docs/` gets the procedure.

### Affected files

| File | Operation | Description |
|------|-----------|-------------|
| `tools/db_diagnose.py` | create | The connectivity/schema diagnosis behind `make db-check` |
| `tools/export_prod_db.sh` | create | The export script for the production database |
| `tools/db_import.py` | create | Backup + import + verify |
| `Makefile` | modify | `db-check`, `db-export`, `db-import`, `db-backup`, `db-seed` |
| `docker/init.sql` | modify | Header that labels it as a development fixture |
| `docs/DATABASE.md` | create | The full procedure, for the operator |
| `.gitignore` | modify | Ignore the dumps and the backups (personal data) |
| `tests/Integration/SchemaParityTest.php` | modify | Report the divergence instead of only asserting |

## Testing Strategy

- **The diagnosis** is tested against the current state: it must report
  "unreachable" and "no dump" correctly.
- **The import** is tested end-to-end with a *synthetic* dump — a dump of the
  fixture itself, re-imported. This proves the loader works without touching
  production data.
- **The export script** is syntax-checked and its arguments are verified
  against a local MySQL.

## Verification Criteria

- [ ] `make db-check` names the cause and the remedy
- [ ] `make db-import FILE=<dump>` works on a synthetic dump
- [ ] The import backs up first and reports the divergence
- [ ] The fixture is labelled as a development fixture
- [ ] The procedure is documented for the operator
- [ ] The existing suite stays green

## Estimation

- Complexity: medium (2–8h)
- Risk: low
- Blocking dependencies: no
