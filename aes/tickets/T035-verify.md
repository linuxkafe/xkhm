---
ticket: T035
phase: verify
status: done
created: 2026-10-21
verdict: pass
requires:
  - aes/tickets/T035-plan.md
---

# T035 — Verify: Os serviços do cliente, pelo seu tipo

## Gates

| Gate | Command | Result |
|------|---------|--------|
| PHPUnit | `make test` | **128 tests** — green |
| PHPStan (nível 5) | `make lint` | **0 erros** |
| Flutter | `make flutter-test` | **45 testes** — green |
| Playwright | `make e2e` | **195 testes** — green (189 + 6 dos serviços) |
| Tokens | `make tokens-check` | em sincronia |

## Acceptance criteria

| AC | Critério | Evidência | Estado |
|----|----------|-----------|--------|
| AC1 | O detalhe do cliente mostra os **tipos** dos serviços | `test_the_panel_shows_the_services_by_type` — as secções são «Web Hosting» e «Backup» | ✅ |
| AC2 | Os serviços de Backup aparecem como Backup | o mesmo teste; e `test_the_services_are_not_all_web_hosting` | ✅ |
| AC3 | O detalhe de um serviço mostra os seus campos | `test_the_service_detail_shows_its_own_fields` — o alojamento mostra domínio, utilizador, disco e banda; o backup **não** | ✅ |
| AC4 | A app tem um ecrã de **Serviços** | `test_the_application_shows_the_services` — o ecrã existe e está no menu | ✅ |
| AC5 | A API devolve o tipo de cada serviço | `test_the_services_carry_their_type` — `type` e `rawType` | ✅ |
| AC6 | O tipo vem do **produto** | a consulta faz a junção a `tblproducts` e `tblproductgroups`; não há lista de tipos no código | ✅ |
| AC7 | Todos os portões verdes | tabela acima | ✅ |

## A distinção, verificada

O cliente 74 (rui.alberto) tem três serviços de **Backup** e um de
alojamento. Antes, tudo aparecia como «Alojamento». Agora:

```
SERVIÇOS
Backup
Serviço                              Tipo          Domínio   Valor      Próxima renovação  Estado
SD1-1000 - Backup Offsite 1 Servidor - 1TB  Backup                 12,00 €  21/10/2027        Active
Web Hosting
Serviço                              Tipo          Domínio   Valor      Próxima renovação  Estado
Página Web                            Web Hosting   xkhm-teste.example.com  24,99 €  21/10/2027  Active
```

E o detalhe de cada um:

```
/admin/services.php?id=65  (o alojamento)
  DOMÍNIO      xkhm-teste.example.com
  UTILIZADOR   xkhm
  SERVIDOR     1
  DISCO        1024 MB de 10240 MB
  BANDA        512 MB de 10240 MB
  SERVIDORES DE NOMES  ns1.linuxkafe.com, ns2.linuxkafe.com

/admin/services.php?id=66  (o backup)
  — nenhum domínio, nenhum utilizador, nenhum disco
```

O backup **não tem** domínios: mostrá-lo seria inventar um dado.

## A API

```json
{
  "services": [
    {
      "id": 66,
      "name": "SD1-1000 - Backup Offsite 1 Servidor - 1TB",
      "type": "Backup",
      "rawType": "other",
      "domain": "",
      "diskLimit": 0
    },
    {
      "id": 65,
      "name": "Página Web",
      "type": "Web Hosting",
      "rawType": "hostingaccount",
      "domain": "xkhm-teste.example.com",
      "diskLimit": 10240
    }
  ]
}
```

`type` é o **rótulo** (o grupo do produto, o que o cliente reconhece);
`rawType` é o valor bruto (`hostingaccount`, `other`, `server`), o que a
máquina reconhece. Viajam os dois, e a interface escolhe.

## Os dados de teste

A semente passou a dar à conta de desenvolvimento **um serviço de cada
família** — um de alojamento e um de backup. Sem isso, a diferença que
se quer mostrar não aparecia, e o teste não tinha o que verificar.

## A regra que ficou

> O tipo de um serviço vem do **produto**. Não há uma lista de tipos
> escrita à mão no código — seria uma segunda fonte da verdade sobre o
> esquema, e desincronizava-se do primeiro produto novo.

## Defeitos encontrados durante a verificação

| # | Defeito | Causa | Correcção |
|---|---------|-------|-----------|
| 1 | A semente falhava com `Incorrect date value` | o `schema_defaults` já resolve as datas; a referência explícita `'0000-00-00 00:00:00'` partia-as | a referência explícita foi removida |
| 2 | A secção chamava-se «Alojamento» | herdado do nome da tabela | passou a ser «Serviços», com um subtítulo e um grupo por tipo |
| 3 | O botão do menu não existia no teste | o `page` do `conftest` usa um viewport de 1280 px, onde a app mostra a **rail** e não o menu | o teste passa a 390 px — que é o que se quer testar |
| 4 | A espera pela semântica expirava | `flt-semantics` não existe; a árvore vive em `flt-semantics-host`, e está **oculta** | a espera é pelo **conteúdo** da árvore, não pela visibilidade |
| 5 | As asserções falhavam por causa das maiúsculas | o CSS aplica `text-transform: uppercase` e o `inner_text` devolve o que é **renderizado** | as comparações são insensíveis a maiúsculas |

Cada um deles era um **falso positivo**: a página respondia `200` com o
conteúdo errado, ou `500`.
