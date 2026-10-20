# SD-DOMAIN-012 — Server status and SSL monitoring

- **Insight**: ServerStatusService provides uptime, load, memory, and DB connectivity metrics using PHP built-ins. SslService tracks certificate expiry and auto-renewal settings. Both use Portuguese (Brazilian) UI.
- **Impact**: Public status page at /serverstatus.php, authenticated SSL management at /ssl.php.
- **Applied To**: T014 SSL configuration and T015 server status.
- **Date**: 2026-10-20