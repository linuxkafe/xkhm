---
ticket: T038
phase: learn
status: done
created: 2026-10-21
requires:
  - aes/tickets/T038-verify.md
---

# T038 — Learn: O aviso de pagamento

## O que se aprendeu

### 1. "Fatura" não é um rótulo — é um regime jurídico

O operador disse: «as faturas devem ser apenas avisos de pagamento até
ser possível ser software de faturação certificada».

A frase parece de vocabulário, e é de **direito**: em Portugal, a
fatura é o documento fiscal, e só o software **certificado** pela
Autoridade Tributária pode emitir um. Um documento que se apresenta
como fatura e não é é um documento fiscal falsificado — e o problema é
do software que o emite.

**Aprendido:** o rótulo de um documento é parte do que ele é. Mudar
«fatura» para «aviso de pagamento» no e-mail, no PDF e no ecrã não é
cosmética: é o que impede que a aplicação emita, por ignorância, um
documento que não tem o direito de emitir.

E o documento **diz isso na cara**: «Este documento é um aviso de
pagamento. Não é uma fatura, nem um documento fiscal, e não serve de
título para efeitos fiscais.»

### 2. A fronteira entre duas partes tem de ser expressa na linguagem das duas

A aplicação gera o PDF **dentro do contentor**; o correio corre **no
host**. A fila transportava o caminho absoluto
(`/var/www/html/storage/notice-pdfs/…`), e o host não tem esse
caminho — o anexo não chegava, e o correio não dizia nada.

**Aprendido:** quando dois processos de lados diferentes partilham
um estado, esse estado tem de ser escrito **numa linguagem que os dois
entendem**. Um caminho absoluto é a linguagem de uma máquina. A
relativa é a dos dois. E o que falha em silêncio — um anexo que não
chega — é o pior tipo de falha: o correio diz «entregue».

### 3. Um anexo acrescentado depois do envio é o mesmo e-mail duas vezes

A primeira versão do `send_with_attachment` acrescentava o ficheiro
**depois** de `send_message()` e voltava a enviar. O cliente recebia
o aviso **duas vezes**, com o anexo só na segunda.

**Aprendido:** a ordem das operações é parte do comportamento. O
anexo vai **antes**, e a mensagem é enviada **uma vez**. E um teste
que verifica «a mensagem foi recebida» não apanha a duplicação — é
preciso contar.

### 4. O `dompdf` renderiza o HTML que a aplicação já sabe fazer

O WHMCS traz o **TCPDF** (2,6 MB, com as fontes) e um template
`.tpl` à parte. A aplicação é HTML, com uma folha de tokens; o
`dompdf` transforma **HTML em PDF**.

**Aprendido:** reaproveitar a apresentação é melhor do que inventar
uma segunda linguagem de estilos. O aviso sai com a **aparência do
produto**, e a folha de estilos do documento é uma folha **própria** —
o `dompdf` não executa JavaScript, e as media queries da aplicação
não se aplicam.

E a biblioteca do WHMCS é **LGPL**, e a licença do projecto é MIT:
juntá-las obriga a rever a licença do projecto. Pelo `composer`, a
dependência fica com a sua licença, e o problema não existe.

### 5. A pose de um documento não se inventa

O primeiro rascunho mostrava o NIF. A instalação **não tem NIF** em
lado nenhum — nem em `CompanyTaxNumber`, nem em `RegistrarAdmin*`.

**Aprendido:** um documento não pode ter um campo que diga «NIF: » e
depois nada. Ou o dado existe, ou o campo **não existe** no documento.
A aplicação não inventa um NIF, e o painel diz que falta.

## Decisões que merecem ser lembradas

| Decisão | Porquê |
|---------|--------|
| «aviso de pagamento», e o documento diz que não é fiscal | Uma fatura é um documento fiscal; só o software certificado a emite |
| O `dompdf`, pelo composer, a partir do HTML | A reaproveitar a apresentação; e evita a LGPL do TCPDF do WHMCS |
| O caminho do anexo é **relativo** | A fila é lida no host e escrita no contentor |
| O anexo é acrescentado **antes** do envio | Uma mensagem, uma vez |
| A folha do documento é **própria** | O `dompdf` não executa JavaScript |
| O NIF vazio **não** se mostra | Um campo vazio lê-se como «não sabemos» |

## O que fica para depois

- **O aviso em HTML** (para quem não quer o PDF).
- **O MB Way** com a referência certa — a informação de pagamento
  configurada.
- **O aviso multi-idioma** (a instalação é portuguesa).
