---
ticket: T040
phase: learn
status: done
created: 2026-10-21
requires:
  - aes/tickets/T040-verify.md
---

# T040 — Learn: O preço e o prazo de renovação

## O que se aprendeu

### 1. O preço do serviço **não** é o preço do produto

O serviço 3 custa 50,00 €; o produto custa 20,00 €/ano. A diferença é
um desconto que o WHMCS permite, e que o `tblpricing` não conhece.

**Aprendido:** ao mudar de ciclo, o preço **vem do produto** (é o que
o ciclo significa); ao mudar o **preço**, é uma decisão à mão — e o
prorrateio usa o valor que está lá, não o do produto. Confundir as
duas coisas perde descontos silenciosamente.

### 2. O prorrateio é uma cotação, não um cálculo

A diferença depende do relógio: depende de quanto tempo resta do
ciclo. Um prorrateio que se aplica sozinho tira dinheiro (ou dá
dinheiro) sem ninguém ter visto o cálculo.

**Aprendido:** `quote()` devolve o cálculo e `apply()` aplica **o que
foi cotado**. São dois momentos, e o segundo não recalcula — caso
contrário, o valor aplicado e o mostrado podiam ser diferentes.

### 3. O prorrateio tem uma direcção, e as duas são diferentes

Uma diferença **negativa** é a favor do cliente: vira **crédito**, e o
crédito existe (`CreditService`). Uma diferença **positiva** é a favor
da empresa: vai para a próxima fatura — e **não há** onde a registar
sem uma coluna nova.

**Aprendido:** as duas direções não são o mesmo caso. Uma resolve-se
com o que existe (o crédito); a outra fica **registada** e dita ao
operador, em vez de ser metida num sítio onde ninguém a vai ver. E o
painel diz qual é: «2,99 € serão facturados na próxima renovação».

### 4. A data da renovação é consequência do ciclo

Mudar o ciclo muda o período, logo muda a data. Deixar a data como
estava produz uma renovação no dia errado — e só se descobre quando o
cliente recebe a fatura.

**Aprendido:** a data **não** se edita; **calcula-se**, e mostra-se
antes de aplicar.

### 5. Um teste que muda um estado partilhado muda o mundo

O teste de aplicação da mudança deixava o preço do serviço de
desenvolvimento alterado — e o teste seguinte partia desse valor, e
falhava por uma razão que não tinha nada a ver com o código.

**Aprendido:** a conta de desenvolvimento é um **recurso partilhado**,
e a semente tem de ser a dona do seu estado. Passou a repor o preço,
o crédito e as notas — e o mesmo se aplica a qualquer estado que um
teste possa tocar.

## Decisões que merecem ser lembradas

| Decisão | Porquê |
|---------|--------|
| O ciclo traz o preço do **produto**; o preço é à mão | Os descontos existem, e o `tblpricing` não os conhece |
| `quote()` e `apply()` são dois momentos | O aplicado é o que foi mostrado |
| A diferença a favor do cliente vira **crédito** | É o que existe, e é o que o cliente sente |
| A diferença a favor da empresa fica **registada** | Não há onde a pôr sem uma coluna nova |
| A data da renovação é **calculada** | Deixá-la como estava renova no dia errado |
| A semente repõe o estado da conta de teste | Um teste que muda um estado partilhado muda o mundo |

## O que fica para depois

- A **troca de plano** (o `upgradepackages` do produto).
- A **criação da fatura de renovação** — que é do motor de facturação,
  e que a antecedência de 30 dias já dá.
- A mudança de ciclo **pelo cliente** (no WHMCS existe; aqui é do
  administrador, e fica escrito).
