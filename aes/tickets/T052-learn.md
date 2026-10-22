---
ticket: T052
phase: learn
status: done
created: 2026-10-22
requires:
  - aes/tickets/T052-verify.md
---

# T052 — Learn: O motor HTML (mobile first)

## O que se aprendeu

### 1. O motor HTML foi removido do Flutter

O `--web-renderer=html` nao existe no Flutter 3.47. A unica opcao e'
o CanvasKit (ou o skwasm, por WebAssembly).

**Aprendido:** a suposicao de que o motor HTML existe e' falsa. A
mitigacao tem de ser no codigo, e nao no motor.

### 2. O service worker e' a causa mais provavel do «continua a nao
ser o moderno»

O operador via a build antiga, mesmo depois de a nova estar pronta. O
service worker cacheia a build inteira, e a actualizacao e' assincrona.

**Aprendido:** um service worker sem cache-busting e' um cache que
nunca expira. A pergunta a fazer de uma build nova nao e' «esta
correcta?» mas «esta a chegar ao utilizador?».

### 3. O foco no Android web e' um defeito do CanvasKit

O CanvasKit desenha num `<canvas>` e perde o foco a cada keystroke.
A unica mitigacao e' a do codigo: o campo ao topo, o padding do
teclado, o `textInputAction` e o `keyboardType`.

**Aprendido:** ha defeitos que so existem num dispositivo real, e a
mitigacao e' a que o operador pode testar — nao a que um teste
automatizado pode verificar.
