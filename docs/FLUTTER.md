# xkhm — a aplicação Flutter

A área do cliente LINUXKAFÉ, em Flutter (web, Android e iOS).

Consome a API versionada (`/api/v1`) do backend PHP. A base de dados
WHMCS (`linuxkonect`) é a **única fonte de verdade**: não há SQL nem
hashing de senha no código Dart.

---

## Topologia

```
                    http://localhost:8083
                             │
        ┌────────────────────┼─────────────────────┐
        │                    │                     │
   /  (Flutter)         /api/v1/*            /admin/*
   a aplicação          a API JSON        o painel de admin
        │                    │                     │
        └────────────────────┼─────────────────────┘
                             │
                    linuxkonect (MySQL)
                  o esquema WHMCS, como está
```

A aplicação e a API são **mesma origem**: o browser gere o cookie de
sessão nativamente, sem CORS e sem um segundo sistema de autenticação.

### O que o PHP ainda é

- o servidor da API (`/api/v1`) e da API WHMCS legada (`/api.php`, usada
  por integrações externas)
- o painel de administração (`/admin`)
- os endpoints de saúde (`/health`, `/health/db`)

### O que o PHP já não é

As páginas HTML de cliente. As 13 rotas registadas antes
(`/clientarea.php`, `/knowledgebase.php`, `/announcements.php`,
`/domainchecker.php`, `/domain.php`, `/viewinvoice.php`,
`/supporttickets.php`, `/ssl.php`, `/serverstatus.php`, `/affiliates.php`,
`/pwreset.php`, `/dologin.php`, `/logout.php`) respondem **302 para `/`**.
O código continua no repositório — re-registá-lo é uma linha.

---

## Tokens de design

A paleta vem de `http://www.linuxkafe.com` e vive num **único** ficheiro:
`assets/design_tokens.json` (na raiz do repositório).

```bash
make tokens        # regenera tokens.css + tokens.dart
make tokens-check  # falha se os ficheiros gerados estiverem desatualizados
```

`tools/sync_tokens.py` gera as duas representações — o bloco
`@media (prefers-color-scheme: dark)` no CSS e as paletas
`LkColors.light` / `.dark` no Dart. O validador recusa uma paleta escura
com chaves diferentes da clara.

`tools/check_no_hardcoded_colors.py` falha se aparecer um literal de cor
(escrito à mão) no Dart **ou** no CSS.

### A interface no telefone

A aplicação abre com um **herói**: o nome, o email e as duas figuras
(crédito e total em aberto) — lado a lado, com a largura de que
precisam. As notícias e os artigos da base de conhecimento resumem-se a
três linhas, com um «ler mais» que os abre. E os ecrãs de dados
aceitam o gesto de arrastar para baixo para recarregar.

O corte do texto é **visual**: o nó de acessibilidade carrega o texto
todo, e quem usa um leitor de ecrã não perde nada.

### Dark mode

O site segue `prefers-color-scheme` — sem JavaScript. A aplicação usa
`ThemeMode.system` e repinta quando o sistema muda de modo. As cores dos
widgets vêm do tema (`Theme.of(context)`), não de constantes, que não se
repintam.

---

## Executar

### Web (Docker)

```bash
make flutter-build          # constrói flutter/xkhm_app/build/web
docker compose up -d        # monta a aplicação e a API
```

A aplicação fica em `http://localhost:8083/`.

### Desenvolvimento

```bash
cd flutter/xkhm_app
flutter run -d chrome --dart-define=API_BASE_URL=http://localhost:8083
```

`API_BASE_URL` vem **vazio** por omissão, o que significa *mesma origem*.
As builds nativas têm de passar um URL absoluto:

```bash
flutter build apk --release --dart-define=API_BASE_URL=https://api.linuxkafe.com
```

---

## Testes

```bash
make flutter-test    # testes unitários e de widget (rápidos, sem browser)
make flutter-e2e     # Playwright contra a aplicação web construída
make e2e             # a suíte E2E completa (backend + aplicação)
```

A suíte Playwright lê a **árvore de semântica** do Flutter
(`flt-semantics`), porque o Flutter desenha num `canvas` e o DOM não
contém os widgets. A árvore é materializada activando o botão oculto
"Enable accessibility".

A aplicação e a API partilham a origem, por isso a suíte faz um **login
real** com credenciais reais e afirma sobre os dados reais da tabela
`linuxkonect`.

---

## Ecrãs

| Ecrã | Endpoints da API |
|------|------------------|
| Início | `GET /api/v1/home` |
| Login | `POST /api/v1/auth/login`, `GET /api/v1/auth/session` |
| Painel | `GET /api/v1/client/summary` |
| Faturas | `GET /api/v1/client/invoices`, `.../invoices/{id}` |
| Pedidos | `GET /api/v1/client/orders` |
| Domínios | `GET /api/v1/client/domains` |
| Certificados SSL | `GET /api/v1/client/ssl` |
| Afiliados | `GET /api/v1/client/affiliate` |
| Tickets | `GET /api/v1/client/tickets`, `.../tickets/{id}`, `POST .../tickets` |
| Base de Conhecimento | `GET /api/v1/knowledgebase`, `.../categories`, `.../{id}` |
| Anúncios | `GET /api/v1/announcements` |
| Procurar Domínio | `GET /api/v1/domains/check` |
| Estado do Servidor | `GET /api/v1/serverstatus` |
| Serviços | `GET /api/v1/client/services` |
| Redefinir senha | `POST /api/v1/auth/forgot-password`, `POST /api/v1/auth/reset-password` |

A interface é **pt-BR** em todos os ecrãs.

### O ecrã de recuperação de senha

`password_reset_screen.dart` tem **dois passos**:

1. **Pedir o código** — o utilizador escreve o email e carrega
   «Pedir recuperação». A resposta é a mesma exista ou não a conta, por
   isso o ecrã **nunca** diz se o email está registado.
2. **Definir a nova senha** — o utilizador escreve o código recebido e a
   nova senha (mínimo 8 caracteres).

Em `APP_ENV=development` o código volta na resposta e o campo é
preenchido automaticamente, para que o fluxo se possa exercitar sem
servidor de correio. Em produção o campo fica vazio e o código chega
por e-mail (ou é entregue pelo administrador — ver `docs/DATABASE.md`).

O botão «Esqueci a senha?» do ecrã de login abre este ecrã.

### O link do e-mail

O e-mail da recuperação leva um link:

```
https://xk.linuxkafe.com/app/#reset=<token>
```

O token viaja no **fragmento** (`#`), que o navegador **nunca envia ao
servidor** — não vai para o log de acessos, nem para um `Referer`. A
aplicação lê-o no arranque (`lib/services/deep_link.dart`), **tira-o
do endereço de barras** com `history.replaceState` — para que também
não fique no histórico — e abre o ecrã já no passo «Definir nova
senha», com o campo do código preenchido.

A *query string* (`?reset=…`) é aceite como alternativa, para os
navegadores e as ligações que não trazem fragmento.

Na aplicação móvel não há endereço de barras: um link chega pela
plataforma, que é um mecanismo diferente. O `stub` devolve nada e
espera que a plataforma entregue o link.

---

## Estrutura do projecto

```
flutter/xkhm_app/
├── lib/
│   ├── main.dart            # entrada, providers, tema do sistema
│   ├── models/models.dart   # DTOs que espelham as cargas da API
│   ├── services/
│   │   ├── api_client.dart  # cliente HTTP + gestão do cookie de sessão
│   │   └── session.dart     # estado de autenticação
│   ├── screens/             # um ficheiro por ecrã
│   ├── theme/
│   │   ├── tokens.dart      # GERADO — não editar
│   │   └── lk_theme.dart    # tema Material 3 a partir dos tokens
│   └── widgets/lk_widgets.dart
├── assets/fonts/            # Inter + Poppins, empacotados (sem CDN)
├── test/                    # testes unitários e de widget
└── web/
    ├── index.html           # com viewport-fit=cover e theme-color
    ├── manifest.json        # com as cores da marca
    └── flutter_bootstrap.js # fixa o CanvasKit na cópia local
```

---

## Notas de operação

- A base de dados local tem **credenciais próprias** e é publicada apenas
  no loopback. A senha de produção vive só em `configuration.php`.
- Para os dados de produção, ver `docs/DATABASE.md` e `make db-check`.
- O painel de administração continua em PHP — ver `aes/tickets/T025.md`
  para a razão.
