---
ticket: T033
phase: plan
status: done
created: 2026-10-21
tier: heavy
verdict: pass
requires:
  - aes/tickets/T033.md
produces:
  - aes/tickets/T033-plan.md
blocked_by: ''
---

# T033 — Plan: O e-mail da recuperação leva um link

## Reconnaissance Summary

| Fact | Evidence |
|------|----------|
| O e-mail leva um código, não um link | `tools/mail_pending_tokens.py::build_message()` |
| **O fluxo do admin não tem onde introduzir o token** | `AdminAuthController` — não existe `action=reset` |
| A app web não tem rotas nomeadas | `main.dart` — `home: const _Root()` |
| A app web é same-origin (CanvasKit e fontes locais) | `web/flutter_bootstrap.js`, `pubspec.yaml` |
| O `Access Log` do Apache regista a query string | Apache com o `LogFormat` por omissão |
| O fragmento nunca é enviado ao servidor | desenho do HTTP (RFC 3986 §3.5) |

## Two-Agent Analysis (heavy tier)

### Critic Phase

```
## Como se fosse uma criança
O carteiro troux um papel com um número. Eu tenho de copiá-lo para um
caderno e depois ir ao sítio escrever o número. Se eu me enganar a
escrever um caractere, não funciona. E a folha do dono da loja nem
sequer tem sítio para escrever o número.

## Como se fosse um especialista

[FAILURE MODE 1] — O token num URL é uma credencial num URL. E um URL
vai para sítios onde o token não deveria ir.

Mecanismo: a *query string* é enviada ao servidor e **regista-se no
log de acessos**. O `Referer` pode enviá-lo a terceiros. O histórico do
navegador guarda-o. Um link de reposição que corre o risco de ficar
registado no log é um link cujo token pode ser lido por quem tem
acesso aos registos.

Mitigação: para o **cliente**, o link usa o **fragmento**
(`#reset=TOKEN`), que o navegador **nunca** envia ao servidor. Não
aparece no log, não aparece no `Referer`. E a aplicação substitui o
URL assim que o lê (`replaceState`), para não ficar no histórico.

A alternativa seria um link com um token curto e um mapa servidor-side,
mas isso exige um armazenamento que o esquema de produção não tem.

[FAILURE MODE 2] — O link do **administrador** tem de chegar a uma
página renderizada pelo PHP, sem JavaScript.

Mecanismo: o painel de administração é PHP puro, sem JS. Um link com
fragmento exigiria JS para passar o token ao servidor — e sem JS o
admin fica sem fluxo nenhum. Um formulário de redefinição que depende
de JavaScript é uma regressão de robustez num painel que é, por
definição, a rede de segurança.

Mitigação: o link do admin usa a *query string* (o token chega ao
servidor), a página redireciona de imediato para um URL limpo, e o
token passa a viver num campo escondido. O log de acessos tem uma
entrada com o token — e isso é **inerente** a um link de GET. Aceito,
e escrito.

[FAILURE MODE 3] — A app não tem rotas nomeadas. Como é que o link
abre o ecrã certo?

Mecanismo: `MaterialApp(home: const _Root())` comuta entre o splash,
o login e a casa conforme o estado da sessão. Não háNamedRoute para
apontar.

Mitigação: o token é lido no arranque e o `_Root` **empurra** o ecrã
de recuperação. Funciona com a sessão iniciada ou não — empurrar por
cima do conteúdo que estiver.

[FAILURE MODE 4] — A aplicação lê o token mas fica com ele no ecrã e
no histórico.

Mecanismo: o utilizador recarrega a página → o token é lido outra vez
(é de uso único, mas fica exposto). E o `Ctrl+H` mostra a entrada.

Mitigação: `replaceState` assim que o token é lido — o histórico é
**substituído**, não acrescentado.

[FAILURE MODE 5] — O e-mail com o link passa a ser o caminho
principal, e o caminho principal pode falhar.

Mecanismo: se o link estiver errado (endereço público mal
configurado, rota inexistente), o utilizador fica **sem** caminho
— e o código, que era o caminho principal, passou a ser a
alternativa.

Mitigação: o e-mail leva **as duas coisas**: o link (acção principal) e
o código (alternativa, com a instrução de o colar no campo). O
código nunca desaparece.

[ASSUMPTION] Se for falso — "deveria ser gerado um link" significa
*apenas* formatar o link no e-mail, sem mexer na aplicação:
consequência: o link levaria a uma página que não sabe o que fazer
com o token, e o utilizador volta a estar exactamente onde estava.
Validation: o operador viu o e-mail **recebido** e disse «isto não são
bem instruções». O problema é o caminho completo, não o formato.

[ALTERNATIVE FRAMING] Em vez de "mandar um link", enquadrar como
"**o correio tem de levar o utilizador até ao fim**": o e-mail, o
link, a página, e o formulário. A pergunta não é "o e-mail tem um
link?" mas "**o utilizador chega a definir a senha sem copiar e colar
nada?**" — e, para o admin, "**chega lá mesmo?**".
```

### Implementor Phase

```
## Como se fosse uma criança
O carteiro traz um bilhete com o número da fechadura e o endereço da
casa. Eu clico no bilhete, ele abre a porta certa e a fechadura nova
só precisa de mim. Se eu perder o bilhete, ainda tenho o número
escrito, para não ficar trancado.

Para o dono da loja, o bilhete tem de ser um papel — porque a loja
não tem ecrãs. E o papel não pode ficar com o número à vista.

## Como se fosse um especialista

(D1) O correio: `multipart/alternative`, com uma parte de texto e uma
de HTML. O link é a acção principal; o código é a alternativa.

```python
message = EmailMessage()
message.set_content(text_part)
message.add_alternative(html_part, subtype='html')
```

(D2) O endereço público.

```bash
XKHM_PUBLIC_URL=https://xk.linuxkafe.com
```

O mailer constrói:

- cliente → `{public}/app/#reset={token}`
- admin   → `{public}/admin/login.php?action=reset&token={token}`

(D3) A app Flutter lê o token.

```dart
// lib/services/deep_link.dart
import 'deep_link_stub.dart'
    if (dart.library.js_interop) 'deep_link_web.dart';

abstract class LkDeepLink {
  static String? takeResetToken();
}
```

A implementação web usa `dart:js_interop` (sem dependências novas):

```dart
final href = location.href;                    // o URL completo
final uri = Uri.parse(href);
final fragment = uri.fragment;                 // "reset=TOKEN"
final token = Uri.splitQueryString(fragment)['reset'];
history.replaceState(null, '', cleaned);       // sai do histórico
```

O **fragmento** primeiro; a *query string* como alternativa.

(D4) O ecrã de recuperação aceita o token.

```dart
class PasswordResetScreen extends StatefulWidget {
  const PasswordResetScreen({super.key, this.token});
  final String? token;
}
```

Com o token, o ecrã salta para o passo da senha.

(D5) A página do administrador.

```
GET  /admin/login.php?action=reset&token=X   → 302 para ?action=reset
GET  /admin/login.php?action=reset            → o formulário, com o
                                               token num campo escondido
POST /admin/login.php  action=reset token=X password=Y
```

O redireccionamento tira o token do campo de endereço **imediatamente**.

(D6) O `XKHM_PUBLIC_URL` entra no `.env` local e no template de
produção.
```

## Addressing FAILURE MODE 1 (o token no URL)

Mitigation: o link do **cliente** usa o fragmento, que nunca chega ao
servidor. O token sai do histórico com `replaceState`. A app é
same-origin (CanvasKit e fontes locais) — não há `Referer` para
terceiros.

## Addressing FAILURE MODE 2 (o admin sem JavaScript)

Mitigation: o link do admin usa a query string e a página funciona
sem JS. O log de acessos fica com uma entrada com o token: é
**inerente** a um link de GET, e está escrito.

## Addressing FAILURE MODE 3 (sem rotas nomeadas)

Mitigation: o `_Root` passa a ser um `StatefulWidget` que empurra o
ecrã de recuperação no primeiro quadro, se houver um token.

## Addressing FAILURE MODE 4 (o token fica no ecrã)

Mitigation: `replaceState` — o histórico é substituído, não
acrescentado.

## Addressing FAILURE MODE 5 (o link pode estar errado)

Mitigation: o e-mail leva o link **e** o código. O código nunca
desaparece.

## [SOLUTION PROPOSAL]

Stage 1 — o correio: link, HTML, endereço público.
Stage 2 — a app: ler o token, limpar o URL, abrir o ecrã.
Stage 3 — o admin: a página que falta.
Stage 4 — os testes.

## Technical Approach

**Chosen:** o correio transporta um link; o link do cliente vive no
fragmento (nunca chega ao servidor) e o do admin na query string (a
página é PHP sem JS). O código continua disponível nos dois casos.

**What will NOT be implemented:** JavaScript no painel de
administração; um armazenamento de links com expiração própria;
e-mails em HTML com imagens.

### Affected files

| File | Operation | Description |
|------|-----------|-------------|
| `tools/mail_pending_tokens.py` | modify | O link, o HTML, o endereço público |
| `tools/deep_link_placeholder` | — | — |
| `flutter/xkhm_app/lib/services/deep_link.dart` | create | A leitura do token |
| `flutter/xkhm_app/lib/services/deep_link_web.dart` | create | A implementação web |
| `flutter/xkhm_app/lib/services/deep_link_stub.dart` | create | A implementação móvel |
| `flutter/xkhm_app/lib/main.dart` | modify | O `_Root` empurra o ecrã |
| `flutter/xkhm_app/lib/screens/password_reset_screen.dart` | modify | Aceita o token |
| `src/Http/Controller/AdminAuthController.php` | modify | A página que falta |
| `src/Service/AdminPasswordResetService.php` | modify | O link para o admin |
| `.env` / `.env.production` | modify | `XKHM_PUBLIC_URL` |
| `tests/Playwright/test_mail_delivery.py` | modify | O link e o HTML |
| `tests/Playwright/test_password_reset.py` | modify | O link abre o ecrã |
| `tests/Playwright/test_admin_auth.py` | modify | A página do admin |

## Testing Strategy

- **O link existe** e aponta para o endereço público.
- **O fragmento funciona**: a app aberta em `/app/#reset=TOKEN` mostra
  o ecrã de recuperação com o token preenchido.
- **O URL fica limpo**: depois de abrir, o fragmento desapareceu.
- **O admin**: o link abre o formulário, o token sai do campo de
  endereço, e o POST define a senha.
- **O código continua a funcionar**: a alternativa.

## Verification Criteria

- [ ] AC1 a AC10 evidenciados
- [ ] `make test`, `make lint`, `make e2e`, `make flutter-test` verdes

## Estimation

- Complexity: high (> 8h)
- Risk: medium — toca no fluxo de autenticação
- Blocking dependencies: none
