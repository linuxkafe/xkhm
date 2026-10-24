---
ticket: T057
phase: review
status: done
created: 2026-10-24
requires:
  - aes/tickets/T057-verify.md
  - aes/peer-reviews/T057/findings.yaml
produces:
  - aes/tickets/T057-review.md
side_effects: []
blocked_by: ''
---

# T057 — Review

## Revisão hostil
Executada com base em `docs/PEER_REVIEW.md`. Rubrica em `templates/review-rubric.md`. Findings em `aes/peer-reviews/T057/findings.yaml`.

## Findings (resumo)
| Tipo | Título | Estado | Fecho |
|---|---|---|---|
| MAJOR | Import credentials stored in unencrypted transient | OPEN (aceite com follow-up) | Migrar para opção encriptada / sessão + purge |
| MAJOR | Missing nonce verification on AJAX start_import step | OPEN (aceite com follow-up) | Adicionar `check_ajax_referer('xkhm_import_wizard','nonce')` e enviar nonce no JS |
| MINOR | `register_shutdown_function` no job runner | OPEN | Avaliar `action-scheduler` ou processo separado |
| MINOR | Schema extra tables não apresentados no UI | OPEN | Mostrar missing + extra com contagens |
| MAJOR | No integration test for full wizard flow (file upload + remote) | OPEN | Adicionar Playwright E2E |
| MINOR | README absent in wordpress-plugin/ | OPEN | Criar `wordpress-plugin/README.md` |
| MAJOR | WpAdminAuthMiddleware redirect sem X-Forwarded-Host | OPEN | Usar `home_url()` + headers forward |

## Avaliação por dimensão
| Dimensão | Nota | Comentário |
|---|---|---|
| Architecture | 9/10 | Boa reutilização do core, DI partilhado, fronteiras claras |
| Security | 7/10 | Middleware WP-admin correto; transientes com credenciais e falta de nonce em alguns handlers a corrigir |
| Performance | 9/10 | Chunked 5k, FK off, streaming mysqli – adequado a dumps grandes |
| UX/Wizard | 8/10 | Fluxo claro 4 passos; falta feedback de tabelas extra e acessibilidade |
| Tests | 7/10 | Unit/integration básicos OK; falta E2E Playwright do wizard |
| Documentation | 6/10 | README em falta |
| Compatibility | 9/10 | WHMCS 5.3.9, PHP 8.4, WP 6.5+, sem alterar esquema |

## Veredicto
**REVIEW: APPROVED WITH CONDITIONS**

As condições são maioritariamente follow-ups não-bloqueantes para este merge de skeleton+integração. As questões de segurança (transiente com credenciais / nonce) devem ser tratadas antes de uso em produção com dumps remotos reais.

## Ações pós-review
1. Endurecer AJAX (nonce em todos os handlers + rate-limit)
2. Encriptar credenciais de importação remota
3. Completar README + Playwright E2E
4. Melhorar UX schema validation