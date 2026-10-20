# SD-META-001 — Live DB is not the local whmcs-mysql container

- **Insight**: The `whmcs-mysql` Docker container running locally has an empty `whmcs` database. The live service at `xk.linuxkafe.com` connects to a **different** MySQL instance at `10.0.3.1` with database name `linuxkonect`. This was confirmed because:
  1. `docker exec whmcs-app mysql ...` times out (10.0.3.1 is not routable from this host)
  2. The local `whmcs-mysql` container only has the `whmcs` DB (created by MYSQL_DATABASE env var), not `linuxkonect`
  3. The `configuration.php` in the live container specifies `DB_NAME=linuxkonect`

- **Impact**: All database schema inference must come from the WHMCS 5.3.9 source code (SQL install files), not from live queries. The reconstruction project's DB integration testing (T025) was blocked for this reason.

- **Applied To**: Any ticket that needs live DB data must either (a) get a database dump from the operator, (b) infer schema from source, or (c) run queries through the whmcs-app container if network access is established.

- **Date**: 2026-10-20
