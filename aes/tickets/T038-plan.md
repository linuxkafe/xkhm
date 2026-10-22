---
ticket: T038
phase: plan
status: done
created: 2026-10-21
tier: heavy
verdict: pass
requires:
  - aes/tickets/T038.md
produces:
  - aes/tickets/T038-plan.md
blocked_by: ''
---

# T038 — Plan: O aviso de pagamento

## Two-Agent Analysis (heavy tier)

### Critic Phase

```
## Como se fosse uma criança
A carta que mando a dizer quanto se deve chama-se factura. Mas na
escola, a folha que me dão a mostrar ao director nao e uma factura
— e se eu disser que e, o director chumba-me o papel. E quando perco a
carta, nao ha copia: peço outra.

## Como se fosse um especialista

[FAILURE MODE 1] — Chamar «fatura» a um documento que não é uma
fatura é um problema **legal**, não de vocabulário.

Mecanismo: em Portugal, a fatura é o documento fiscal, e a sua
emissão exige software **certificado** pela AT. Um documento que se
apresenta como fatura, e não é, é um documento fiscal falsificado — e
o software que o emite é o problema, não o cliente.

Mitigation: o documento chama-se **aviso de pagamento** e diz, no
próprio, que não é um documento fiscal e que a fatura certificada é
emitida separadamente. A designação do WHMCS («fatura») fica para o
que o WHMCS emite.

[FAILURE MODE 2] — O PDF pode ser gerado de duas maneiras: pelo
`TCPDF` que o WHMCS traz (2,6 MB, com as fontes), ou pelo `dompdf`
do composer.

Mecanismo: o `TCPDF` do WHMCS é **LGPL** e a licença do xkhm é MIT —
juntar as duas obriga a rever a licença do projecto. E são 2,6 MB de
biblioteca e fontes para um documento simples.

Mitigation: o `dompdf` pelo composer (LGPL, licence própria), que
transforma **HTML em PDF** — e a aplicação já é HTML, com uma folha
de tokens. O PDF sai com a **aparência do produto**, sem uma segunda
linguagem de estilos.

[FAILURE MODE 3] — O reenvio por correio tem de levar o PDF anexado,
e o correio corre **no host**, não no contentor.

Mecanismo: o correio do host (T032) lê uma fila de ficheiros e envia. O
PDF é gerado pela aplicação, dentro do contentor. Se o correio tentar
gerar o PDF, não pode (não tem a aplicação).

Mitigation: a aplicação gera o PDF e **guarda-o**; a fila passa a
aceitar uma linha `attachment:` com o caminho. O correio do host
anexa o ficheiro e apaga-o depois de entregar. A fila continua a
ser o ponto único de passagem.

[FAILURE MODE 4] — O PDF não pode ser servido a toda a gente.

Mecanismo: uma rota de PDF sem dono mostra o resumo de pagamento de
qualquer cliente a quem experiments o número.

Mitigation: a rota do cliente valida a **sessão** e a posse da fatura;
a rota do painel exige a sessão de administrador. E o `Content-Disposition`
é `attachment`, com um nome de ficheiro sem nada de sensível.

[FAILURE MODE 5] — O IVA não está configurado.

Mecanismo: a `tbltax` está **vazia** e a `TaxType` é `Inclusive`. Um
aviso que calcula o IVA a partir de uma tabela vazia mostra zero, ou
mostra uma taxa inventada.

Mitigation: o aviso mostra o que a fatura **tem** (o `total` e o
`subtotal` que já lá estão), e **não** recalcula o IVA. A tabela de
taxas é um dado de configuração, e o que não está configurado não se
inventa.
```

### Implementor Phase

```
## Como se fosse uma criança
A carta sai em PDF, com o nome da empresa em cima, a lista do que
comprei, quanto devo e até quando. E em baixo diz: isto ainda nao e
factura.

## Como se fosse um especialista

(D1) O gerador: HTML → PDF.

```php
final class PaymentNoticePdf
{
    public function render(InvoiceNotice $notice): string;   // o PDF
}
```

O HTML é montado a partir da **folha de tokens** — o mesmo CSS do
resto, com uma folha de impressão. O `dompdf` não executa JavaScript,
por isso o HTML tem de ser estático.

(D2) O modelo: o que o aviso mostra.

```php
final class InvoiceNotice
{
    public int $id;
    public string $number;
    public string $issuedAt;
    public string $dueAt;
    public array $items;        // descrição, valor
    public float $subtotal;
    public float $tax;
    public float $total;
    public string $status;
    public array $client;       // nome, email, morada
    public array $issuer;        // nome, NIF, morada, email
    public array $paymentMethods;
}
```

(D3) As rotas.

```
GET  /api/v1/client/invoices/{id}/pdf   → o PDF (o cliente dono)
POST /api/v1/client/invoices/{id}/resend → o reenvio
GET  /admin/invoices/{id}/pdf            → o PDF (o administrador)
POST /admin/invoices/{id}/resend         → o reenvio
```

(D4) A fila com anexo.

```
purpose: aviso
recipient: cliente@exemplo.pt
account: cliente
token: 288
subject: Aviso de pagamento — fatura 9001
attachment: /var/www/html/storage/notice-pdfs/288-20261021.pdf
created: 2026-10-21T…
```

O correio do host lê as linhas novas e anexa.
```

## Addressing FAILURE MODE 1 (o rótulo)

Mitigation: «aviso de pagamento», com a declaração explícita no
documento.

## Addressing FAILURE MODE 2 (a biblioteca)

Mitigation: o `dompdf`, pelo composer, a partir do HTML que a
aplicação já sabe produzir.

## Addressing FAILURE MODE 3 (o correio no host)

Mitigation: a aplicação gera e guarda o PDF; a fila transporta o
caminho.

## Addressing FAILURE MODE 4 (a posse)

Mitigation: a sessão e a posse são validadas; o `Content-Disposition`
é `attachment`.

## Addressing FAILURE MODE 5 (o IVA)

Mitigation: o aviso mostra o que a fatura tem; não recalcula nada.

## [SOLUTION PROPOSAL]

Stage 1 — o modelo e o gerador.
Stage 2 — as rotas e o template.
Stage 3 — o reenvio e a fila com anexo.
Stage 4 — os rótulos, e os testes.

## Technical Approach

**Chosen:** um gerador de PDF a partir de HTML, com a mesma folha de
tokens; e a fila do correio do host a ganhar a capacidade de levar um
anexo.

**What will NOT be implemented:** a fatura certificada (T039); as
vias de pagamento automáticas; o cálculo do IVA.

### Affected files

| File | Operation | Description |
|------|-----------|-------------|
| `src/Service/InvoiceNotice.php` | create | O modelo do aviso |
| `src/Service/PaymentNoticePdf.php` | create | O gerador |
| `src/Http/Controller/Api/ApiV1Controller.php` | modify | O PDF e o reenvio |
| `src/Http/Controller/Admin/InvoiceController.php` | modify | O PDF e o reenvio |
| `tools/mail_pending_tokens.py` | modify | O anexo e o assunto |
| `public/templates/default/css/notice.css` | create | A folha do documento |
| `tests/Playwright/test_payment_notice.py` | create | O PDF e o reenvio |

## Testing Strategy

- O PDF abre, tem o conteúdo, e diz que não é um documento fiscal.
- O reenvio chega à fila, com o anexo; e **não** altera a fatura.
- O PDF de outro cliente é recusado.

## Verification Criteria

- [ ] AC1 a AC8 evidenciados
- [ ] `make test`, `make lint`, `make e2e`, `make flutter-test` verdes

## Estimation

- Complexity: high (> 8h)
- Risk: medium — é o primeiro documento que sai do sistema
- Blocking dependencies: none
