---
id: SD-META-015
title: "A mesma origem resolve a sessão sem CORS e sem invenções"
tier: meta
epistemic_state: verified
confidence: 0.9
date: 2026-10-21
source: T020
tags: [cookies, cors, same-origin, session, architecture]
---

# A mesma origem resolve a sessão sem CORS e sem invenções

## O problema

A aplicação Flutter web precisa de autenticar-se contra a API. A API usa a
sessão PHP (`PHPSESSID`) — a mesma que o site usa. Uma sessão PHP é um cookie,
e os cookies pertencem ao **domínio**, não à origem (a porta não conta).

Três alternativas, e o custo de cada uma:

| Alternativa | Custo |
|-------------|-------|
| Aplicação em outra porta/origem | CORS, `withCredentials`, `Access-Control-Allow-Origin` dinâmico, risco de `*` com credenciais |
| Token próprio (JWT) | Um segundo sistema de autenticação, uma segunda semântica de hash, uma segunda lista de bugs |
| **Servir a aplicação no mesmo servidor que a API** | A aplicação web passa a ser `http://host/app/` e a API `http://host/api/v1` |

A terceira é a que|scale. O browser gere o cookie sozinho, não há CORS, e
não existe um segundo sistema de autenticação.

## A concretização

O build web do Flutter é montado no content root do PHP:

```yaml
volumes:
  - ./flutter/xkhm_app/build/web:/var/www/html/public/app:ro
```

E o `API_BASE_URL` fica **vazio** por omissão, o que significa *mesma
origem*. Só as builds nativas passam um URL absoluto.

No lado do Dart, a diferença é uma linha:

```dart
Map<String, String> get _headers => {
      'Accept': 'application/json',
      if (!kIsWeb && _cookie != null) 'Cookie': 'PHPSESSID=$_cookie',
    };
```

No browser, definir um cabeçalho `Cookie` à mão é proibido — o browser
recusa. Logo, no web não se define; o browser faz o seu trabalho.

## A consequência: o E2E passa a ser um login real

Com a mesma origem, o Playwright pode fazer login a sério contra a API e
deixar o cookie no contexto. A aplicação abre, encontra a sessão e entra
directamente no painel — e o teste passa a verificar dados reais da base de
dados, não um mock.

## A lição

**Antes de inventar um mecanismo de autenticação, perguntar se a aplicação
pode simplesmente viver ao lado da API.** A restrição "compatível com a
base de dados e a sessão actuais" é, ironicamente, aconstraint que torna a
solução simples: a sessão já existe, só precisa de estar no mesmo sítio.

## Ver

- `docs/FLUTTER.md`
- `docker-compose.yml`
- `flutter/xkhm_app/lib/services/api_client.dart`
