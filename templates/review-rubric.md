# Review Rubric — T057 WordPress Plugin + WHMCS Import

| Dimension | Weight | Description |
|-----------|--------|-------------|
| Architecture | 20% | Separation of concerns, DI container usage, no circular deps, clear boundaries between WP plugin and core xkhm. |
| Security | 25% | Credential handling (transients, env vars), middleware protection, SQL injection avoidance, least privilege. |
| Performance | 15% | Chunked import (5k rows), FK checks disabled, streaming mysqli, async job design. |
| UX / Wizard | 10% | 4-step wizard flow, progress polling, error feedback, accessibility. |
| Tests | 15% | Unit tests for ImportService, plugin bootstrap, integration test target `make wp-plugin-test`. |
| Documentation | 10% | README, wp-config constants, wizard usage, CLI help. |
| Compatibility | 5% | WP 6.5+, PHP 8.2+, WHMCS 5.3.9 schema, no DB schema changes. |