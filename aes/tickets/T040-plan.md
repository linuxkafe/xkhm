---
ticket: T040
phase: plan
status: done
created: 2026-10-21
tier: heavy
verdict: pass
requires:
  - aes/tickets/T040.md
produces:
  - aes/tickets/T040-plan.md
blocked_by: ''
---

# T040 — Plan: O preço e o prazo de renovação

## Two-Agent Analysis (heavy tier)

### Critic Phase

```
## Como se fosse uma criança
A loja tinha o meu paquete a 50 euros por ano. Se passar a 30, so
tenho de dar 10 euros de volta — e so a parte que ainda nao passou.
Se passar a 60, tenho de pedir 10 euros a mais. E o cliente tem de
saber o que mudou.

## Como se fosse um especialista

[FAILURE MODE 1] — O prorrateio tem de ser **mostrado antes** de ser
aplicado.

Mecanismo: um prorrateio que se aplica sozinho tira dinheiro (ou dá
dinheiro) sem ninguém ver o cálculo. E o cálculo depende do relógio.

Mitigation: o painel mostra a diferença, a favor de quem é, e o
operador confirma. O valor aplicável é o que foi mostrado.

[FAILURE MODE 2] — O preço do serviço **não** é o preço do produto.

Mecanismo: o serviço 3 custa 50,00 € e o produto custa 20,00 €/ano.
Ao mudar de ciclo, buscar o preço do produto **perde** o desconto.

Mitigation: mudar o **ciclo** traz o preço do produto para esse
ciclo; mudar o **preço** é uma decisão à mão, que o painel mostra e
que o prorrateio usa tal como está.

[FAILURE MODE 3] — Um ciclo que o produto não tem.

Mecanismo: o `tblpricing` tem `-1.00` nos ciclos que o produto não
oferece. Aceitar o ciclo e preço `-1.00` seria um serviço a menos um
euro.

Mitigation: os ciclos disponíveis são lidos do `tblpricing` do produto,
e só os que têm preço entram no formulário.

[FAILURE MODE 4] — A data da renovação.

Mecanismo: mudar o ciclo muda o período, logo muda a data. Deixar a
data como estava produz uma renovação no dia errado.

Mitigation: a data é recalculada a partir do novo ciclo, e é
mostrada antes de ser aplicada.

[ASSUMPTION] Se for falso — "definir o preço e o prazo" é uma
funcionalidade de **cliente** (no WHMCS, o cliente muda o ciclo no
portal): consequência: o que se constrói é a versão do administrador,
e o cliente fica a ver o preço e o prazo em vez de os mudar.
Validation: o operador é quem gere a operação; e o WHMCS dá ao
cliente a mudança de **ciclo**, não de preço. Fica escrito.
```

### Implementor Phase

```
## Como se fosse uma criança
O formulario mostra: o preco de hoje, o preco novo, quantos dias
faltam, e quanto se da ou se pede. Se estiver bem, muda. E fica
escrito no caderno do cliente.

## Como se fosse um especialista

(D1) O serviço.

```php
final class ServiceTermsService
{
    public function quote(int $serviceId, string $billingCycle, ?float $amount): array;
    public function apply(int $serviceId, string $billingCycle, ?float $amount, int $adminId): array;
}
```

(D2) O prorrateio.

```php
$days = (int)floor(($nextDue - $now) / 86400);
$cycleDays = self::CYCLE_DAYS[$billingCycle];      // 30, 90, 180, 365…
$fraction = max(0.0, min(1.0, $days / $cycleDays));
$difference = ($newPrice - $oldPrice) * $fraction;
```

Se a diferença for negativa, é crédito do cliente.

(D3) O registo: `tblnotes`, com o `adminid` de quem mudou.
```

## Addressing FAILURE MODE 1 (o prorrateio sem pré-visualização)

Mitigation: `quote()` devolve o cálculo, e `apply()` aplica **o que
foi cotado**.

## Addressing FAILURE MODE 2 (o preço do produto)

Mitigation: o ciclo traz o preço do produto; o preço é uma decisão à
mão.

## Addressing FAILURE MODE 3 (os ciclos indisponíveis)

Mitigation: só os ciclos com preço no `tblpricing` entram.

## Addressing FAILURE MODE 4 (a data da renovação)

Mitigation: recalculada a partir do novo ciclo.

## [SOLUTION PROPOSAL]

Stage 1 — o serviço e o prorrateio.
Stage 2 — o painel.
Stage 3 — a app.
Stage 4 — os testes.

## Technical Approach

**Chosen:** um serviço que **cota** a mudança e a **aplica**, com o
prorrateio calculado do relógio e registado nas notas do cliente.

**What will NOT be implemented:** as trocas de plano; a criação da
fatura de renovação; a mudança de ciclo pelo cliente.

### Affected files

| File | Operation | Description |
|------|-----------|-------------|
| `src/Service/ServiceTermsService.php` | create | A cotação e a aplicação |
| `src/Repository/AdminPanelRepositoryPDO.php` | modify | O serviço, os preços e as notas |
| `src/Http/Controller/Admin/ServiceController.php` | modify | O formulário |
| `flutter/xkhm_app/lib/screens/services_screen.dart` | modify | O preço e o prazo |
| `tests/Integration/ServiceTermsServiceTest.php` | create | O prorrateio |
| `tests/Playwright/test_admin_services.py` | create | O painel |

## Testing Strategy

- O prorrateio: meio ciclo, ciclo cheio, ciclo passado.
- A diferença a favor do cliente vira crédito.
- A mudança fica nas notas.
- O ciclo traz o preço do produto.

## Verification Criteria

- [ ] AC1 a AC7 evidenciados
- [ ] `make test`, `make lint`, `make e2e`, `make flutter-test` verdes

## Estimation

- Complexity: medium (4-8h)
- Risk: medium — mexe em dinheiro
- Blocking dependencies: none
