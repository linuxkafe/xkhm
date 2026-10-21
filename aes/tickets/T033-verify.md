---
ticket: T033
phase: verify
status: done
created: 2026-10-21
verdict: pass
requires:
  - aes/tickets/T033-plan.md
---

# T033 — Verify: O e-mail da recuperação leva um link

## Gates

| Gate | Command | Result |
|------|---------|--------|
| PHPUnit | `make test` | **112 tests, 228 assertions** — green |
| PHPStan (nível 5) | `make lint` | **0 erros** |
| Flutter | `make flutter-test` | **45 testes** — green |
| Playwright | `make e2e` | **169 testes** — green (157 + 12 do link) |
| Tokens | `make tokens-check` | em sincronia |

## Acceptance criteria

| AC | Critério | Evidência | Estado |
|----|----------|-----------|--------|
| AC1 | O e-mail contém um **link** clicável | `test_the_email_carries_a_link` — o link está no texto e no HTML | ✅ |
| AC2 | O link leva ao formulário, com o token preenchido | `test_the_link_opens_the_recovery_screen`; `test_the_admin_link_opens_the_form` | ✅ |
| AC3 | O link do cliente **não** passa pelo servidor | `test_the_token_never_reaches_the_server` — nenhuma request leva o token | ✅ |
| AC4 | O token é **removido do URL** | `test_the_token_leaves_the_address_bar`; `test_the_admin_token_leaves_the_address_bar` | ✅ |
| AC5 | O administrador tem uma página para definir a senha | `test_the_admin_page_has_the_form` — a página **não existia** | ✅ |
| AC6 | O token do administrador sai do campo de endereço | `test_the_admin_token_leaves_the_address_bar` | ✅ |
| AC7 | O código continua disponível | `test_the_admin_code_can_be_pasted`; o código está no e-mail | ✅ |
| AC8 | O e-mail tem texto simples e HTML | `multipart/alternative`, com as duas partes | ✅ |
| AC9 | O link aponta para o endereço público configurado | `XKHM_PUBLIC_URL`; o link é construído a partir dele | ✅ |
| AC10 | Todos os portões verdes | tabela acima | ✅ |

## O caminho completo, verificado

### O e-mail

```
Subject: LINUXKAFE — redefinir a senha de administrador
From: safedata@linuxkafe.com
To: mail@linuxkafe.com

Ola,

Foi pedido um pedido de redefinicao de senha de administrador
para a conta "linuxkafe".

Para definir a nova senha, abra este endereco:

    http://localhost:8083/admin/login.php?action=reset#reset=0e5e3ce0…

O pedido e valido durante uma hora e so pode ser usado uma vez.

Se o endereco nao funcionar, use este codigo na pagina de redefinicao:

Codigo: 0e5e3ce0918c55b40d72bf88aec31c96d3b1f4bd954c661a9c5a5bc88eada33d

Se nao foi voce, ignore esta mensagem: nada acontece.
```

### O link do cliente — o token não chega ao servidor

```
$ page.goto("http://localhost:8083/app/#reset=TOKEN-DE-LINK")

  o ecrã de recuperação abre, já no passo "Definir nova senha"
  o campo do código tem o token
  o URL fica: http://localhost:8083/app/     (sem fragmento, sem token)
```

E o teste que garante a propriedade de segurança:

```python
def test_the_token_never_reaches_the_server(page):
    ...
    for url in requests:
        assert "token-de-link" not in url.lower()
```

Nenhuma request ao servidor leva o token. O **fragmento** nunca é
enviado — é por isso que ele, e não a *query string*.

### O link do administrador — a página que não existia

Antes desta ticket, o token chegava ao administrador e **não havia
onde o meter**. Agora:

```
GET /admin/login.php?action=reset#reset=TOKEN
  → a página "Definir nova senha", com o código preenchido
  → o URL fica limpo: /admin/login.php?action=reset
POST /admin/login.php  action=reset  token=…  password=…
  → "Senha alterada com sucesso"  →  /admin/login.php?reset=1
```

E a nova senha abre o painel:

```
$ POST /admin/login.php  username=xkhm-reset-admin  password=AdminPeloLink2026
  302 → /admin
$ GET /admin
  200
```

### O ponto final: o link define a senha

O teste que prova a finalidade do link, do primeiro clique à senha
nova a funcionar:

```python
def test_the_link_defines_the_new_password(page):
    token = <pedido real>
    page.goto(f"{APP_URL}#reset={token}")
    _fill(page, "Nova senha", "SenhaPeloLink2026")
    page.get_by_role("button", name="Definir nova senha").click()

    assert "alterada com sucesso" in body

    # a senha nova funciona
    assert login.status == 200
    # a antiga não
    assert old.status == 401
```

## O e-mail entregue a sério

```
$ curl -X POST .../api/v1/admin/auth/forgot-password -d '{"username":"linuxkafe"}'
  token: 0e5e3ce0918c55b4…

  entregue apos ~10s
  [OK]    mail@linuxkafe.com
  1 entregue(s), 0 com falha.
```

## A segurança do link, em resumo

| Propriedade | Como |
|------------|------|
| O token não vai para o log do servidor | viaja no **fragmento**, que o navegador não envia |
| O token não vai num `Referer` | a aplicação é *same-origin* (CanvasKit e fontes locais) |
| O token não fica no histórico | `history.replaceState` — **substitui**, não acrescenta |
| O token não fica no campo de endereço | o mesmo `replaceState` |
| O token expira | uma hora |
| O token é de uso único | é apagado quando a senha muda — e **sai da fila**, para ninguém receber um código morto |
| O painel do admin não depende de JavaScript | o campo do código é visível e editável; o script só o preenche |

## Defeitos encontrados durante a verificação

| # | Defeito | Causa | Correcção |
|---|---------|-------|-----------|
| 1 | A asserção lia `Codigo:` e o e-mail dizia `codigo` | a palavra passou a minúscula dentro de uma frase | o rótulo voltou a ser explícito: `Codigo: <token>` |
| 2 | O e-mail é `multipart/alternative` com `quoted-printable` — o `?` chega como `=3D` e as linhas são quebradas | a comparação era feita sobre o texto cru | o teste **descodifica** as partes antes de comparar |
| 3 | O URL ficava `…/app/?` e `…/app/#` | `Uri.replace(queryParameters: {})` deixa um `?` pendurado; `replace(fragment: '')` deixa um `#` | o URL é **reconstruído** a partir das partes |
| 4 | O rodapé dizia «Se a conta existir, enviámos as instruções» mesmo com o link | o texto era fixo | passa a dizer «o pedido é válido durante uma hora…» quando o link traz o token |
| 5 | A *query string* lia o token mas **não** limpava o URL | `Uri.replace(queryParameters: null)` **não** remove a query — deixa-a como está | o mesmo URL reconstruído |
| 6 | O campo do token parecia vazio | o `<input>` da árvore de semântica só ganha o `value` quando o campo é focado | o teste foca o campo, como o utilizador faria |

## Uma nota sobre a corrida com o `cron`

O `crontab` entrega a fila de minuto a minuto, e a suite conduz a mesma
fila. Um teste podia falhar porque o `cron` levou o token entre a
aplicação o escrever e o teste o ler.

A pausa do `crontab` passou para o `conftest.py`, como uma fixture de
**sessão**: a suite inteira fica protegida, e o `crontab` é restaurado
no fim.
