---
ticket: T033
phase: learn
status: done
created: 2026-10-21
requires:
  - aes/tickets/T033-verify.md
---

# T033 — Learn: O e-mail da recuperação leva um link

## O que se aprendeu

### 1. Um e-mail que não chega ao fim é um e-mail que não serve

O e-mail dizia:

```
Codigo: 6c8ea5a08c2e7c638e0994845466b6ea8a15a67684a9b2c70d4eb71c262407fe
```

O utilizador tinha de copiar o código, abrir a aplicação, e colá-lo
num campo. Três passos, e cada um é um ponto de falha: um carácter
copiado a menos e a coisa não funciona.

E, ao ver o e-mail, percebi-se o pior: **o fluxo do administrador não
tinha onde meter o código**. A página de "esqueci a senha" pedia o
código, o código chegava por e-mail, e não existia formulário para o
introduzir. O fluxo estava **incompleto** — e ninguém o notou, porque
ninguém o percorreu de ponta a ponta.

**Aprendido:** a pergunta certa não é "o e-mail tem um link?" mas
"**o utilizador chega a definir a senha sem copiar e colar nada?**" — e,
para o admin, "**chega lá mesmo?**". O formato do e-mail é a
consequência; o caminho é o que importa.

E: um fluxo que ninguém percorreu de ponta a ponta pode estar
completamente partido a meio. O "esqueci a senha" estava testado nas
suas duas metades — o pedido e o serviço — e não no **todo**.

### 2. O token num URL é uma credencial num URL

Um link de reposição transporta um segredo. Onde é que esse segredo
vai parar?

- a **query string** é enviada ao servidor e **fica no log de
  acessos**;
- o **`Referer`** pode enviá-lo a terceiros;
- o **histórico do navegador** guarda-o.

O **fragmento** (`#reset=…`) não é enviado ao servidor — é por
desenho do HTTP. O token não chega ao log, nem ao `Referer`.

**Aprendido:** a escolha entre `?` e `#` não é de estilo. Num link
que transporta uma credencial, é a diferença entre o segredo ficar
registado num log ou não ficar. E a aplicação deve **substituir** a entrada
do histórico (`replaceState`), não acrescentar uma — senão o botão
"voltar" volta ao token.

### 3. "Não é preciso JavaScript" é uma propriedade, não uma desculpa

O plano tinha decidido que o link do admin usaria a *query string*,
porque o painel de administração é PHP sem JavaScript, e um link que
depende de JS falha num navegador antigo.

O plano estava a pensar no **transporte**. Mas o link pode levar o
token no fragmento **e** a página funcionar sem JavaScript:

- com JS: o script mete o fragmento no campo;
- sem JS: o utilizador **cola o código**, que o e-mail também traz.

O fluxo não está partido — está um passo mais longo. E o token nunca
vai para o log.

**Aprendido:** "sem JavaScript" não é um argumento para expor um
segredo. É um argumento para ter **duas vias**, e para que a via
automática seja a que não expõe. O pior dos dois mundos — a
segurança da via automática *e* a robustez da manual — é o que se
obtém com o campo visível e editável mais um script de preenchimento.

### 4. A URL precisa de ser reconstruída, não "replaced"

`Uri.replace()` em Dart tem duas surpresas:

- `replace(queryParameters: null)` **não** remove a query — deixa-a
  como estava;
- `replace(queryParameters: {})` deixa um `?` pendurado no fim;
- `replace(fragment: '')` deixa um `#` pendurado.

O URL tem de ser **reconstruído** a partir das suas partes.

**Aprendido:** as APIs de URL tendem a ser "substitui um
componente" e não "remove um componente". `null` significa "não
touches", não "apaga". Uma pilha de `?` e `#` pendurados no fim do
endereço é o sintoma.

### 5. O `<input>` da árvore de semântica do Flutter é um espelho

O teste dizia que o campo do token estava vazio. Não estava: o Flutter
guarda o valor no `TextEditingController`, e o `<input>` do DOM é
apenas o espelho — que **só ganha o `value` quando o campo é
focado**.

**Aprendido:** uma asserção sobre o DOM de uma aplicação Flutter tem
de interagir como um utilizador, não ler o espelho. Clicar no campo
primeiro, e só depois ler. (E o `TextEditingController` que recebe o
valor de um link tem de o receber **antes** do primeiro `build` — o
`initState` é o sítio certo.)

### 6. A palavra "não" num e-mail é um problema de e-mail

O texto dizia «Se o endereço não funcionar, use este código na página
de redefinição:» e depois o código, sem rótulo. Uma pessoa a ler
rapidamente não sabe o que fazer com aquilo.

Passou a ser `Codigo: <token>` — uma linha que se lê sozinha.

**Aprendido:** o e-mail é lido por alguém **apressado** e possivelmente
no telemóvel. Cada elemento tem de se explicar sozinho. Um bloco de
texto sem rótulo é um bloco que se perde.

### 7. Um teste que compara texto cru compara o transporte

O e-mail passou a ser `multipart/alternative` com `quoted-printable`.
O texto cru tem `?` como `=3D` e as linhas longas partidos com `=`.

**Aprendido:** um teste que compara o **corpo** de um e-mail tem de o
**descodificar** primeiro (o módulo `email` faz isso). Comparar o
transporte com o conteúdo é comparar a embalagem com o produto — e
quando a embalagem muda, o teste falha sem que nada de errado tenha
acontecido.

### 8. Um daemon e uma suite que partilham um estado

O `crontab` entrega a fila de minuto a minuto; a suite conduz a mesma
fila. Um teste podia falhar porque o `cron` levou o token entre a
aplicação o escrever e o teste o ler — uma falha que não tem nada a
ver com o código.

**Aprendido:** a fiabilidade de um teste depende do que mais está a
correr ao mesmo tempo. A correcção é **pausar o daemon** durante a
suite (uma fixture de sessão), e não rezar para que as janelas não se
cruzem.

## Decisões que merecem ser lembradas

| Decisão | Porquê |
|---------|--------|
| O token viaja no **fragmento** | O navegador não o envia ao servidor: não vai para o log de acessos, nem para o `Referer` |
| O `replaceState` **substitui** a entrada do histórico | Para o token não ficar no histórico |
| O link do admin também usa o fragmento | O painel sem JS não obriga a expor o token; o campo do código continua visível e editável |
| O e-mail leva o link **e** o código | A via automática é a principal; a manual é a rede de segurança |
| `multipart/alternative` | Um e-mail de texto para quem lê num terminal, e um de HTML para quem lê no cliente |
| `XKHM_PUBLIC_URL` | O link tem de apontar para o endereço que os clientes usam, não para o contentor |
| A página do admin tem script — **mas o campo não depende dele** | O script preenche; o campo permite colar |
| O ecrã Flutter salta para o passo da senha | Com o token, não há nada a pedir |

## O que fica para depois

- **Links universais na aplicação móvel**: o fragmento é um mecanismo
  de navegador. Numa aplicação de telemóvel, o link chega pela
  plataforma, com as suas próprias regras de verificação. O `stub`
  assume isso e não finge.
- **Uma página de "o link não funciona"** no e-mail, com o contacto.
- **Estatísticas**: quantos links são abertos e quantos códigos são
  colados à mão. Sai-daí a resposta para "o link é mesmo melhor?".
