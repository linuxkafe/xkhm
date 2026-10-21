---
ticket: T032
phase: learn
status: done
created: 2026-10-21
requires:
  - aes/tickets/T032-verify.md
---

# T032 — Learn: Rotação da senha do MySQL + entrega de correio

## O que se aprendeu

### 1. "Rodar a senha" tem dois alvos, e só um está ao alcance

O MySQL **local** (o do contentor) e o MySQL de **produção** (`10.0.3.1`)
partilham a palavra "senha" e não partilham nada mais. A local está a
correr aqui; a de produção está atrás de um firewall que não deixa
entrar.

O erro fácil é rodar a local, dizer "a senha foi rodada" e dar o
problema por resolvido — quando o que estava exposto foi sempre a de
produção.

**Aprendido:** um relatório de segurança tem de dizer **qual** segredo
rodou. "Rodada" sem sujeito é uma afirmação que não pode ser verificada
e, por isso, não deve ser feita.

### 2. Um healthcheck que não falha é pior do que não ter healthcheck

O healthcheck usava `mysqladmin ping` com a senha de **produção**
contra a base **local** — e o contentor esteve sempre "healthy".

A razão: o `mysqladmin ping` responde "alive" quando o servidor
responde, e o servidor responde **antes** de autenticar. O comando
devolve exit 0 com uma senha errada.

Um healthcheck que não pode falhar não é um healthcheck: é um
decorador. Dava confiança exactamente nos casos em que não devia.

**Aprendido:** um healthcheck tem de **exercitar a coisa que quer
vigiar**. Para o MySQL, isso é autenticar (`mysql -e "SELECT 1"`), não
pingar. E `MYSQL_PWD` em vez de `-p...` na linha de comando, para a
senha não aparecer no `ps` de toda a gente.

### 3. `ALTER USER` invalida a sessão que o executou

Rodar a senha do `root` a partir do `root` falha se o `FLUSH PRIVILEGES`
for numa **segunda** ligação: no momento em que o `ALTER USER` faz
efeito, a senha antiga deixa de valer, e a nova ligação é recusada.

O sintoma é enganador — o `ALTER USER` **funcionou**, e foi o passo
seguinte que falhou, a dizer "acesso negado".

**Aprendido:** o `FLUSH PRIVILEGES` tem de ser na **mesma** ligação,
antes de a fechar. E, quando a conta rodada é a mesma que se liga, a
ligação de administração deixa de ser válida no meio do procedimento —
é uma coisa que só se aprende porque deu erro.

### 4. Um segredo num ficheiro versionado não está "em.hide" — está exposto

A senha de produção estava em **10 ficheiros** e em **10+ commits**.
Tirá-la dos ficheiros é necessário e não é suficiente: o histórico
continua.

A rotação é o que **neutraliza** a exposição — a senha velha deixa de
servir. Reescrever a história é o que a **remove**, e é uma operação
destrutiva que afecta todos os clones.

**Aprendido:** a ordem é 1) tirar dos ficheiros, 2) rodar, 3) reescrever
o histórico (opcional, e uma decisão do operador). E a regra de
prevenir a recaída: **no repositório fica a forma, nunca o valor** —
`${XKHM_DB_PASSWORD:?...}` em vez de um default.

### 5. O contentor não tem caminho para o host — e isso não é um bug do contentor

Descobri que a aplicação, dentro do contentor, **não chega** ao host:
as portas 25, 53 e 8083 dão timeout a partir de lá. O firewall do host
rejeita o tráfego da bridge Docker, e não há `sudo` para o abrir.

A solução tentadora — `network_mode: host` — traz dois problemas: a
porta 80 do host está ocupada, e partilhar a pilha de rede do host
traria de volta a colisão da T027 (o MySQL do host na 3306).

A solução que funciona é **mover o componente para o host**: o
servidor de correio está no host, portanto o correio é entregue no
host. E o `crontab` do utilizador é a forma mais simples de ter um
processo no host sem `root` — e está **verificado** (uma sonda, não uma
suposição).

**Aprendido:** quando a rede separa dois componentes, a pergunta não é
"como abro a porta?" mas "**de que lado tem de ficar cada coisa?**". A
aplicação escreve o token; o host entrega-o. Nenhum dos dois precisa
de conhecer o outro.

### 6. A promessa do plano e o código divergiam

O plano do T031 dizia: "a entrega é plugável e **o ficheiro é sempre
escrito**, mesmo quando o SMTP existe". O código fazia
`$smtpDelivery ?? $fileDelivery` — com SMTP configurado, o ficheiro
**não** era escrito.

Nenhum teste apanhou isto, porque ambos os caminhos "funcionavam". A
divergência só apareceu quando a pergunta *"e se o SMTP falhar?"* foi
posta de facto.

**Aprendido:** um plano que promete uma garantia é um **contrato**. Se
o código não a implementa, há de estar um teste que a verifica — e esse
teste teria falhado com o código antigo. Hoje existe
(`TokenDeliveryTest::testTheFileIsWrittenEvenWhenTheMailFails`).

### 7. "Apenas um consumidor" é a regra que evita tudo

Um token pendente pode ser entregue duas vezes (pela aplicação e pelo
correio do host) ou nenhuma (a aplicação apaga o ficheiro depois de
entregar, mas o correio falha).

A regra que resolve as duas: **o ficheiro é a fila; quem entrega
retira-o da fila**. A aplicação escreve, tenta o correio, e **remove o
ficheiro** se conseguiu. O correio do host lê a fila e remove o que
entrega. Nunca dois, nunca zero.

E quando a entrega falha, o ficheiro vai para `failed/` **com o
motivo** — nada se perde, e o operador vê porque.

**Aprendido:** a maioria dos bugs de "duplicado" e de "perdido" em
filas são o mesmo bug: **dois consumidores, ou nenhum**. Escrever a
regra de posse é mais barato do que depurar as consequências.

### 8. Os domínios reservados existem para *não* receber correio

A suite de testes pedia a recuperação para `xkhm-test@example.com`, e o
correio do host entregava — de verdade, pela relay do operador. O
`example.com` tem **null MX** (RFC 7505): a mensagem sai, é recusada, e
volta como bounce. Uma caixa de correio real enchia-se de lixo, e o
tráfego passava pela conta de outra pessoa.

A regra certa não é "não entregar aos endereços de teste" — é **não
entregar aos domínios reservados** (RFC 2606, RFC 6761). Eles estão
reservados precisamente para não existirem. `example.com`, `localhost`,
`.local`, `.invalid`, `.test`: nenhum aceita correio, por desenho.

**Aprendido:** um endereço `@example.com` numa aplicação que envia
correio a sério é um **bug**, mesmo quando "funciona". E a regra
precisa de uma válvula de escape (`XKHM_MAILER_ALLOW_RESERVED=1`),
senão não há como testá-la.

### 9. O remetente é uma parte da configuração que ninguém documenta

O relay de saída (Brevo) devolveu `451 4.0.0 Invalid from` para um
remetente razoável. O que o relay aceita é o **remetente registado** —
e o mapa canónico do Postfix reescreve o `root` local para esse.

Descobrir isto exigiu ler `/etc/postfix/canonical` e
`/etc/postfix/sasl_passwd`. A configuração do correio de uma
máquina está espalhada por três ou quatro ficheiros que ninguém
documenta.

**Aprendido:** a "configuração" do correio não é o `smtp_host` e o
`smtp_port` — é o **remetente autorizado**, a cadeia de relay, e o
mapa canónico. E a primeira coisa a fazer com um servidor SMTP é um
teste de envio a sério, não uma leitura de configuração.

### 10. Um teste que disputa o estado com um daemon é um teste flaky

O novo teste conduzia a fila de tokens — a mesma fila que o `cron`
consome de minuto a minuto. A janela de colisão é pequena, mas
**existe**, e um teste que falha 3% das vezes é um teste que ninguém
confia.

A solução foi **pausar o `crontab`** durante a suite, restaurando-o no
`finally`. Não é limpo, mas é **determinista** — e um teste
determinista vale mais do que um teste "quase sempre verde".

**Aprendido:** a fiabilidade de um teste depende do que mais está a
correr ao mesmo tempo. Quando um teste e um daemon partilham um
estado, ou o daemon pára, ou o estado é isolado.

## Decisões que merecem ser lembradas

| Decisão | Porquê |
|---------|--------|
| O token vai para a base do cliente e para um ficheiro no admin | A coluna do WHMCS existe; o `tbladmins` não tem uma. |
| O ficheiro é **sempre** escrito | É a fila. Sem ele, uma falha do SMTP perde o token. |
| Quem entrega retira da fila | Exatamente um consumidor. |
| O correio corre no **host** | O contentor não chega ao host. Mover o componente é mais simples que abrir a porta. |
| O `crontab` do utilizador | Um processo no host sem `root`. Verificado a correr. |
| `MYSQL_PWD` no healthcheck | Autentica de verdade, e a senha não aparece no `ps`. |
| `${VAR:?}` em vez de um default | O compose **falha** em vez de arrancar com uma senha inventada. |
| O `CC_ENCRYPTION_HASH` também saiu do repositório | É um segredo partilhado. Não pode ser rodado sem uma migração — mas também não deve estar no histórico. |
| A rotação **verifica** | A senha antiga tem de falhar e a nova tem de funcionar. Sem isso, é uma promessa. |

## O que fica para depois

- **Rodar a senha de produção** — precisa de acesso ao `10.0.3.1`. A
  ferramenta está pronta e testada; o comando está no `T032-verify.md`.
- **Reescrever o histórico do git** — decisão do operador.
- **Migrar o `CC_ENCRYPTION_HASH`** — para o poder rodar.
- **SMTP autenticado** — o local não precisa; um relay externo, sim.
