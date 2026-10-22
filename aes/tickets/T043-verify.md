---
ticket: T043
phase: verify
status: done
created: 2026-10-22
verdict: pass
requires:
  - aes/tickets/T043-plan.md
---

# T043 — Verify: A gestão de produtos, serviços e servidores

## Gates

| Gate | Command | Result |
|------|---------|--------|
| PHPUnit | `make test` | **185 tests, 550 assertions** — green (9 novos do catálogo) |
| PHPStan (nível 5) | `make lint` | **0 erros** |
| Playwright | `make e2e` | **243 testes** — green (16 novos do catálogo) |
| Tokens | `make tokens-check` | em sincronia |

## Acceptance criteria

| AC | Critério | Evidência | Estado |
|----|----------|-----------|--------|
| AC1 | Os produtos mostram o **tipo real** e o grupo | `test_the_products_are_listed_with_their_prices` e `test_a_product_shows_its_type_and_group` — `hostingaccount`, `other`, `server`, e o grupo `Web Hosting` | ✅ |
| AC2 | Os preços são por **ciclo**; um ciclo que o produto não tem fica vazio | `testACycleTheProductDoesNotHaveIsMinusOne` — o `tblpricing` marca `-1.00`; o formulário mostra o campo vazio | ✅ |
| AC3 | Um produto pode ser **criado** com o seu preço | `test_a_product_can_be_created_and_removed` — criado, preço `12,50 €` lido da base, e removido no fim | ✅ |
| AC4 | Um produto **em uso** não é removido, e o painel diz porquê | `test_a_product_that_a_client_has_cannot_be_removed` — «não pode ser removido: há serviços que dependem dele» | ✅ |
| AC5 | Os servidores mostram hostname, IP, custo, estado e grupos | `test_the_servers_are_listed` e `test_a_server_shows_its_group_and_its_state` — `linuxkafe.com:11000`, `88.198.129.1:11000`, `8,21 €`, `LINUXKONECT`, `Ativo` | ✅ |
| AC6 | Um servidor pode ser criado, editado, removido e colocado num grupo | `test_a_server_can_be_created` (cria e remove), `test_a_server_can_be_opened_and_edited` (o campo da senha é `password`), `test_a_server_belongs_to_a_group` | ✅ |
| AC7 | Todos os portões verdes | tabela acima | ✅ |

## Os quatro defeitos que os testes apanharam

Os testes não passaram à primeira. Cada uma das quatro falhas era um
defeito real:

1. **`INSERT` recusado** — `ERROR 1364: Field 'description' doesn't
   have a default value`. As tabelas do WHMCS são largas e quase
   todas as colunas são `NOT NULL` sem default. Corrigido: as
   colunas em falta são lidas do esquema e preenchidas com o valor
   neutro do tipo.
2. **`order` é palavra reservada** — erro de sintaxe no `INSERT`.
   Corrigido: todos os identificadores dinâmicos entre crases.
3. **`tblhosting` não tem `productid`** — tem `packageid`. A
   contagem de serviços rebentava com um 500.
4. **O formulário do grupo criava um produto** — o `action=group` não
   era tratado. Corrigido: cada formulário tem a sua `action`, e
   cada `action` faz uma coisa.

## O que o esquema obriga

```
tblproducts    70 colunas NOT NULL sem default  (46 texto, 13 int, 3 decimal, 1 varchar)
tblservers     24 colunas NOT NULL sem default  (21 texto, 3 int)
tblpricing     todas as colunas NOT NULL sem default, e `type` é uma enumeração
```

É por isso que o preenchimento neutro vem do `information_schema`:
a lista escrita à mão apodrece ao primeiro `ALTER TABLE` do WHMCS.
