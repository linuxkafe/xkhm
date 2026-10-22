---
ticket: T052
phase: verify
status: done
created: 2026-10-22
verdict: pass
requires:
  - aes/tickets/T052-plan.md
---

# T052 — Verify: O motor HTML (mobile first)

## Gates

| Gate | Command | Result |
|------|---------|--------|
| Flutter | `make flutter-test` | **50 testes** — green |
| Playwright | `make e2e` | **251 testes** — green |
| Flutter build | `make flutter-build` | verde, sem warnings |

## Acceptance criteria

| AC | Critério | Evidência | Estado |
|----|----------|-----------|--------|
| AC1 — A build usa o motor HTML | **Nao aplicavel**: o Flutter 3.47 removeu o motor HTML. A build e' canvaskit (a unica suportada). A mitigacao do foco e' a do codigo. | Verificado: `flutter build web -h` nao tem `--web-renderer`. | N/A |
| AC2 — Os testes do Playwright continuam verdes | 251 testes — green | tabela acima | ✅ |
| AC3 — A aplicacao carrega e renderiza no Chrome | `flutter-view` aparece, e a arvore de semantica e' construida | `test_flutter_app_boots_without_errors` ✓ | ✅ |
| AC4 — Todos os portoes verdes | tabela acima | ✅ |

## O defeito, verificado

O service worker cacheava a build inteira, e a actualizacao e' assincrona
— o operador via a build antiga (com a wrapper de Semantica e com a
Poppins), mesmo depois de a nova build estar pronta. Com o service
worker desligado, o navegador carrega sempre a build nova.

O foco no Android web e' um defeito do CanvasKit, e a mitigacao e' a
do codigo (T051): o campo ao topo, o padding do teclado, o
`textInputAction` e o `keyboardType`. A verificacao definitiva e' no
dispositivo real.
