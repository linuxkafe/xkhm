---
ticket: T037
phase: learn
status: done
created: 2026-10-21
requires:
  - aes/tickets/T037-verify.md
---

# T037 — Learn: MFA para dispositivos novos, sem alterar a base de dados

## O que se aprendeu

### 1. Uma restrição é um desenho, não uma nota

«Sem alterar a base de dados» parecia uma **limitação** — o que
obrigaria a inventar um ficheiro por cliente, ou a recusar o
requisito.

Mas a restrição **empurra** para a resposta certa. O segredo do
utilizador tem de viver **com o utilizador** — e a coluna que o WHMCS
6/7 usa para o 2FA (`tblclients.authdata`) **já existia**, vazia, à
espera.

E a lista de dispositivos de confiança, que é onde a restrição morde,
resolve-se com uma inversão: a confiança não é um **registo**, é uma
**prova**. O servidor emite um cookie assinado; quem o tem é de
confiança. Não há lista — e revogar um dispositivo é o cookie expirar.

**Aprendido:** a lista de "dispositivos de confiança" é a resposta
*obvia*, e é a que exige uma tabela. A pergunta melhor é «**como é
que se prova que este dispositivo já esteve cá?**» — e a resposta
pode não precisar de armazenamento nenhum.

### 2. O `SchemaMap` mentia sobre uma coluna real

O `SchemaMap` **sintetizava** `tblclients.authdata` como uma string
vazia — o sentido era «esta coluna não se gerencia aqui». A consequência:
`toReal()` descartava o valor na escrita, e `toCanonical()` devolvia
`''` na leitura.

A coluna existia. Tinha `text`. Estava vazia em todos os clientes. E o
`SchemaMap` dizia o contrário.

**Aprendido:** um mapa de esquema é uma **afirmação** sobre a base de
dados, e as afirmações envelhecem. Aquela foi escrita noutro ticket,
com outra intention, e ninguém a reexaminou — porque ninguém usava a
coluna.

E a lição maior: **a mentira só apareceu quando alguém precisou da
coluna.** Uma restrição do enunciado («sem alterar a base de dados»)
levou ao esquema, e o esquema levou ao falsehood.

### 3. Um documento vazio é diferente de um documento estranho

O `disable()` escrevia `{}` — e o código que lê tratava `{}` como
«dados de outra pessoa», porque a **chave** que é nossa não estava
lá. Resultado: desativar o segundo factor **bloqueava a inscrição para
sempre**.

**Aprendido:** «não tenho nada» e «tenho outra coisa» são estados
**diferentes**, e o código tem de os distinguir. Um documento JSON
vazio é o primeiro; um documento sem a nossa chave é o segundo. A
consequência de os confundir não é um erro de estilo — é o cliente
preso sem o poder sair.

### 4. A validade e a idade não são a mesma coisa

O token assinado carregava a expiração, e a «idade» era calculada a
partir dela: `time() - $expires`. Com a expiração **no futuro**, a
idade é negativa — e o limite máximo nunca era violado.

**Aprendido:** um token tem **dois** instantes: quando foi emitido e
quando expira. A idade mede-se a partir da **emissão**; a validade
mede-se até à **expiração**. Confundir as duas dá um limite máximo que
não limita nada — e é o limite máximo que protege o desafio.

### 5. Os vectores de teste do RFC são a única prova

O primeiro código TOTP «não batia certo» — e batia, certo: os
vectores do RFC 6238 são de **8 dígitos** e as aplicações usam 6. Os
6 últimos batiam exactamente.

A tentação era «ajustar até dar certo». A resposta certa foi tornar o
número de dígitos configurável e comparar com o RFC **exacto** — a
implementação passa a ser verificada **por fora**, e não contra si
mesma.

**Aprendido:** um teste que compara a implementação com ela própria
não prova nada — prova que a implementação é consistente consigo. O
RFC tem vectores de teste publicados precisamente para isso: usá-los
é o que transforma «funciona» em «funciona como deve».

### 6. A excepção de um verificador é uma decisão de arquitectura

O verificador de cores proíbe literais — e o QR **precisa** de fundo
claro: os módulos têm de ser escuros, ou o telemóvel não o lê. Não é
uma decisão de marca; é uma exigência do formato.

A resposta não foi afrouxar o verificador, nem pôr o literal às
escondidas. Foi um **marcador explícito** na linha, com a razão ao
lado — `// tokens-check: allow` — e a regra de que a excepção é
*auditável*: está no código que a precisa.

**Aprendido:** um verificador que nunca pode falhar não serve para
nada; um verificador cujas excepções são visíveis no código serve
para muito mais. E a excepção que se esconde é pior do que a
literal.

### 7. Um botão que aparece sem o que abrir

O «ler mais» aparecia mesmo em textos que não precisavam de ser
expandidos — e o botão abria o que já estava aberto.

**Aprendido:** a decisão de mostrar um botão de expansão é uma
**medição**, não uma suposição. O `TextPainter` diz se o texto foi
cortado; sem ele, o botão está a mentir sobre o que há para ler.

## Decisões que merecem ser lembradas

| Decisão | Porquê |
|---------|--------|
| O segredo em `tblclients.authdata` | A coluna que já existia, e que o WHMCS 6/7 usa para o 2FA |
| A confiança é um **cookie assinado** | Nenhuma lista, nenhuma tabela; e revogar é expirar |
| A chave é **derivada** do segredo partilhado | O mesmo segredo, um propósito diferente |
| O desafio vive **cinco minutos** | É uma prova momentânea, não uma credencial |
| A comparação é em **tempo constante** | Um código comparado byte a byte diz quantos caracteres estão certos |
| `authdata` deixou de ser sintetizado | A coluna é real; o mapa dizia o contrário |
| O `disable()` escreve a coluna **vazia** | «Não tenho nada» ≠ «tenho outra coisa» |
| O TOTP é verificado contra o **RFC** | Um teste contra a própria implementação não prova nada |
| O QR tem uma **excepção auditável** | A excepção está no código, com a razão |

## O que fica para depois

- **A administração do 2FA pelo painel**: um administrador poder
  ver se um cliente tem segundo factor, e o poder **desligar** (com
  registo). Hoje só o cliente o desliga.
- **A revogação de dispositivos um a um**: a confiança é por cookie; para
  listar os dispositivos de confiança de um cliente seria preciso um
  registo — e a restrição diz que não.
- **O segundo factor por correio**, para quem não tem aplicação de
  autenticação. O correio do host existe (T032), mas mandá-lo exigiria
  um código de uso único na fila — e a fila é para a recuperação.
- **A recuperação do 2FA**: se o cliente perde o telefone, fica sem
  acesso. Hoje só um administrador o pode resolver, e o painel ainda não
  tem essa função.
