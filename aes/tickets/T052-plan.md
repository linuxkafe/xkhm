---
ticket: T052
phase: plan
status: done
created: 2026-10-22
tier: standard
verdict: pass
requires:
  - aes/tickets/T052.md
produces:
  - aes/tickets/T052-plan.md
blocked_by: ''
---

# T052 — Plan: O motor HTML (mobile first)

## Two-Agent Analysis (standard tier)

### Critic Phase

```
ASSUMPTIONS I'M MAKING:
- [KNOWN]    O Flutter 3.47 removeu o motor HTML: a opcao e' o CanvasKit
             (ou o skwasm, por WebAssembly). Verificado: o `--web-renderer`
             nao existe neste Flutter.
- [INFERRED] A causa mais provavel do «continua a nao ser o moderno»
             e' o service worker, que serve a build antiga (com a wrapper
             de Semantica e com a Poppins). Verificado: o service worker
             cacheia a build inteira, e a actualizacao e' assincrona.
- [ASSUMED]  A build CanvasKit e' a unica viavel. O skwasm (WebAssembly)
             precisa de cross-origin isolation (COOP/COEP), que o
             navegador pode recusar em contextos mistos.

WHAT WASN'T SPECIFIED:
- Se deve usar o skwasm. A decisao: nao — o skwasm exige COOP/COEP, e
  o beneficio (performance) nao justifica o risco (a app nao carrega).

ALTERNATIVES I DIDN'T CHOOSE:
- **O motor HTML por um bootstrap customizado.** Rejeitado: o motor HTML
  nao existe nesta versao do Flutter. A unica via e' o CanvasKit.
- **Um framework diferente (HTML/JS puro).** Rejeitado: e um projecto
  proprio, e a app existente e' Flutter.

RISKS & SIDE EFFECTS:
- O service worker e' desligado: a app precisa de rede. Aceitavel
  para esta fase — a instalacao e' uma aplicacao web, e a rede esta
  la.
- O foco no Android web pode persistir: a causa e' do CanvasKit, e
  a unica mitigacao e' a do codigo (campo ao topo, padding do teclado,
  textInputAction, keyboardType).

COST OF BEING WRONG: MEDIUM — a app pode nao carregar em alguns
browsers, e o foco pode persistir.
```

## A solucao

1. O `Makefile` usa a build por omissao (canvaskit, sem `--wasm`).
2. O `flutter_bootstrap.js` nao regista o service worker — o
   navegador ve sempre a build nova.
3. O `docker/apache.conf` nao precisa dos COOP/COEP (sem wasm).
4. A mitigacao do foco no codigo (T051 v2) e a unica via.

## O que fica de fora

- **O motor HTML**: nao existe nesta versao do Flutter.
- **O skwasm**: exige cross-origin isolation, e o risco e' maior que o
  beneficio.
- **A migracao para HTML/JS puro**: e um projecto proprio.
