---
ticket: T039
phase: plan
status: done
created: 2026-10-21
tier: heavy
verdict: pass
requires:
  - aes/tickets/T039.md
produces:
  - aes/tickets/T039-plan.md
blocked_by: ''
---

# T039 — Plan: A configuração

## Two-Agent Analysis (heavy tier)

### Critic Phase

```
## Como se fosse uma criança
A loja tem um caderno com 235 coisas escritas. A porta do correio le
o caderno. Mas o meu programa le um papel com seis coisas, e por isso
nao sabe o nome da loja, nem o NIF, nem os dados do banco. E o NIF
nao esta em lado nenhum do caderno.

## Como se fosse um especialista

[FAILURE MODE 1] — "A configuração" são **duas** coisas: o que o
programa precisa para ligar à base de dados (o `configuration.php`) e o
que o **negócio** precisa (a empresa, o IVA, a numeração).

Mecanismo: pôr as 235 definições no `configuration.php` seria uma
reversão do WHMCS — que as tem na base de dados, e foi por isso que
a base importada as traz.

Mitigation: o `configuration.php` fica com o que é **dela** (a ligação,
o hash, o `customadminpath`), e a `tblconfiguration` passa a ser a
fonte da verdade do resto — a mesma do WHMCS, e a mesma que já veio
importada.

[FAILURE MODE 2] — As definições estão em **prefixos** diferentes:
`CompanyAddress1` está vazia, mas `RegistrarAdminAddress1` tem a
morada.

Mecanismo: o WHMCS usa `RegistrarAdmin*` quando o registador usa os
dados do cliente (`RegistrarAdminUseClientDetails = on`). São duas
coisas diferentes, e a empresa da loja é a do registador.

Mitigation: a camada de configuração tem uma **resolução**: o que se
procura no prefixo `Company*` e, quando está vazio, vai ao
`RegistrarAdmin*`. Mas o painel mostra as **duas** fontes, para que
o operador veja o que há — e o que falta.

[FAILURE MODE 3] — O NIF **não existe** em lado nenhum.

Mecanismo: sem NIF não há documento fiscal. Nenhuma camada de
configuração o inventa.

Mitigation: o painel tem um **relatório do que falta**, com o que
bloqueia cada coisa. O NIF aparece lá — e o operador preenche-o. A
aplicação **não** o fabrica nem o adivinha.

[FAILURE MODE 4] — Editar uma definição pode partir o sistema.

Mecanismo: `InstanceID`, `License`, `token_namespace` — mexer nelas
pode invalidar a instalação.

Mitigation: o painel **só** edita uma lista explícita de definições
seguras, e mostra as restantes como leitura. Uma lista escrita à mão
é o que torna a excepção auditável.

[FAILURE MODE 5] — A numeração das faturas pode colidir.

Mecanismo: o `SequentialInvoiceNumberValue` é um contador. Duas
faturas criadas no mesmo instante recebem o mesmo número.

Mitigation: o número é gerado **dentro de uma transação**, com o
contador lido e incrementado na mesma operação — como o `LAST_INSERT_ID`
do T028.
```

### Implementor Phase

```
## Como se fosse uma criança
O programa le o caderno da loja, e quando falta alguma coisa diz-me
o que falta em vez de chumbar.

## Como se fosse um especialista

(D1) A camada: `SettingsService`.

```php
final class SettingsService
{
    public function all(): array;                 // o caderno inteiro
    public function get(string $key, ?string $default = null): ?string;
    public function set(string $key, string $value): bool;
    public function company(): array;             // a empresa, resolvida
    public function missing(): array;             // o que falta
}
```

O cache é por pedido (a configuração muda raramente, mas muda).

(D2) A resolução da empresa.

```php
'CompanyAddress1' => $this->get('CompanyAddress1')
    ?: $this->get('RegistrarAdminAddress1'),
```

(D3) O painel: agrupado e editável.

Os grupos: **Empresa**, **IVA**, **Moeda**, **Facturação**,
**Numeração**, **Correio**, **Pagamento**, e **as outras** (leitura).

(D4) O relatório do que falta.

```php
[
  ['key' => 'CompanyTaxNumber', 'label' => 'NIF', 'why' => 'obrigatório numa fatura'],
  ['key' => 'CompanyBankDetails', 'label' => 'Dados bancários', 'why' => 'para o cliente pagar por transferência'],
  …
]
```

(D5) A numeração.

```php
$number = str_replace('{NUMBER}', (string)$counter, $format);
```

O contador vive em `SequentialInvoiceNumberValue`, e a transação
garante que dois pedidos não tiram o mesmo número.
```

## Addressing FAILURE MODE 1 (as duas configurações)

Mitigation: o `configuration.php` para a ligação; a
`tblconfiguration` para o negócio.

## Addressing FAILURE MODE 2 (os prefixos)

Mitigation: a resolução, e o painel mostra as duas fontes.

## Addressing FAILURE MODE 3 (o NIF)

Mitigation: o relatório do que falta. A aplicação não inventa um NIF.

## Addressing FAILURE MODE 4 (as definições perigosas)

Mitigation: uma lista explícita das que se podem editar.

## Addressing FAILURE MODE 5 (a numeração)

Mitigation: o contador dentro de uma transação.

## [SOLUTION PROPOSAL]

Stage 1 — o serviço das definições.
Stage 2 — a resolução da empresa e o relatório do que falta.
Stage 3 — a página do painel, agrupada e editável.
Stage 4 — a numeração sequencial.

## Technical Approach

**Chosen:** a `tblconfiguration` como fonte da verdade; o painel como a
 interface de edição; e um relatório do que falta.

**What will NOT be implemented:** as 235 definições editáveis; a
certificação do software de faturação; os prefixos que a app não usa.

### Affected files

| File | Operation | Description |
|------|-----------|-------------|
| `src/Service/SettingsService.php` | create | A leitura e a escrita |
| `src/Repository/SettingsRepositoryInterface.php` | create | O acesso à tabela |
| `src/Repository/SettingsRepositoryPDO.php` | create | A implementação |
| `src/Service/InvoiceNumberService.php` | create | A numeração sequencial |
| `src/Http/Controller/Admin/SettingsController.php` | create | A página do painel |
| `src/Http/View/AdminLayout.php` | modify | O formulário |
| `public/index.php` | modify | As rotas |
| `tests/Integration/SettingsServiceTest.php` | create | As regras |
| `tests/Playwright/test_admin_settings.py` | create | O painel |

## Testing Strategy

- As definições vêm da base.
- A resolução da empresa usa o `RegistrarAdmin*` quando o `Company*` está
  vazio.
- O relatório aponta o que falta.
- A edição muda a definição, e **só** as que se podem mudar.
- A numeração não repete.

## Verification Criteria

- [ ] AC1 a AC7 evidenciados
- [ ] `make test`, `make lint`, `make e2e`, `make flutter-test` verdes

## Estimation

- Complexity: high (> 8h)
- Risk: medium — a configuração é o que governa o sistema
- Blocking dependencies: none
