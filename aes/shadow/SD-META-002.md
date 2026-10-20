# SD-META-002 — Architecture decision: PHP 8.4 + Slim + PDO + Repository Pattern

- **Insight**: The two-agent protocol confirmed PHP 8.4 + Slim Framework 4 + MySQL PDO with Repository Pattern as the optimal architecture for xkhm. Key reasons:
  1. Direct `configuration.php` reuse (same language, same config format)
  2. Minimal framework overhead (Slim = routing + middleware only, ~2MB)
  3. Repository pattern isolates schema coupling (interfaces + PDO implementations)
  4. Operator expertise in PHP
  5. WHMCS business logic is PHP-native; porting is 1:1

- **Impact**: T005 will implement the project scaffold with this architecture. All subsequent tickets (T006+) will follow this structure.

- **Applied To**: T004 architecture decision. Any deviation requires a new heavy ticket with two-agent protocol.

- **Date**: 2026-10-20