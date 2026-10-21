---
ticket: T032
phase: plan
status: done
created: 2026-10-21
tier: heavy
verdict: pass
requires:
  - aes/tickets/T032.md
produces:
  - aes/tickets/T032-plan.md
blocked_by: ''
---

# T032 — Plan: Rodar a senha do MySQL + entrega de correio

## Reconnaissance Summary

| Fact | Evidence |
|------|----------|
| A base de produção **não** é alcançável | `10.0.3.1:3306` em timeout; sem ping |
| A senha de produção está em 10 ficheiros versionados e em 10+ commits | `grep -rn` + `git log -S` |
| O contentor não alcança o host | portas 25, 53, 8083 em timeout a partir do contentor |
| O `crontab` do utilizador corre | sonda verificada |
| O `From` autorizado é `safedata@linuxkafe.com` | `451 Invalid from` com outros; `/var/mail/root` actualizado com esse |
| A porta 80 do host está ocupada | `ss -ltn` |

## Two-Agent Analysis (heavy tier)

### Critic Phase

```
## Como se fosse uma criança
A chave da porta estava escrita num caderno que eu deixei à vista de
todos. Posso mudar a fechadura — mas a porta da produção está do outro
lado da estrada e não vou lá de carro. E o correio: o carteiro existe e
não pede identificação, mas só entrega na porta de casa, não através
da janela.

## Como se fosse um especialista

[FAILURE MODE 1] — "Rodar a senha do MySQL" tem **dois** alvos
distinctos, e a confusão entre eles é o erro mais provável.

*O alvo que está ao alcance* é o MySQL do contentor local
(`linuxkonect`@`%`, `root`@`%`). Rodá-lo é o trabalho que de facto
posso fazer.

*O alvo comprometido* é o MySQL de produção em `10.0.3.1`. Esse está
inalcançável: a rota existe, a porta não responde. Rodar a senha local
**não** neutraliza a senha de produção comprometida — dariam a impressão
de estar resolvido sem estar.

Mechanism: um plano que reporte "a senha foi rodada" sem distinguir
qual delas está a criar um risco de confiança falso. A solução tem de
dizer explicitamente: **a de produção continua por rodar, e o comando
para o fazer fica escrito e testado**.

[FAILURE MODE 2] — O `healthcheck` do compose usa a senha de **produção**
contra a base **local**, e passa. `mysqladmin ping` responde "alive"
mesmo quando a autenticação falha — o healthcheck estava a dar um
"verde" falso desde sempre. Ao trocar a senha local, se o healthcheck
continuar a usar a de produção, continua a dar verde na mesma. O
healthcheck tem de usar a **variável de ambiente**, e de preferência
passar a usar `--password=...` via `MYSQL_PWD` ou a variável `MYSQL_PWD`
para não expor a senha na linha de comando (que fica no `ps`).

[FAILURE MODE 3] — O contentor não tem caminho para o host. A
"solução" tentadora é `network_mode: host` — mas a porta 80 do host está
ocupada, e o contentor passaria a partilhar a pilha de rede do host
(incluindo o MySQL do host na 3306, que é exatamente a colisão que a
T027 evitou).

Mechanism: o bloqueio é o firewall do host a rejeitar o tráfego da bridge
Docker, e não há `sudo` para o abrir. Qualquer caminho que passe pelo
host está bloqueado. A única entrega possível é a que é feita **no
host** — e é o que o correio do host faz.

[FAILURE MODE 4] — O T031 prometeu "o ficheiro é **sempre** escrito, mesmo
quando o SMTP existe", mas o código faz `$smtpDelivery ?? $fileDelivery`:
com SMTP configurado, o ficheiro **não** é escrito. A promessa do plano
e o código divergem.

Mechanism: é exactamente a falha que o plano previu ("a entrega é
pluggável e o ficheiro é sempre escrito") e que a implementação não
respeitou. Com a nossa correcção, a regra passa a ser: **um componente
entrega, o outro é a rede de segurança**.

[FAILURE MODE 5] — O token do administrador é entregue pelo **nome de
utilizador** (`linuxkafe`), que não é um endereço de correio. A
entrega por correio ficaria muda.

Mechanism: `tbladmins` tem uma coluna `email` (`mail@linuxkafe.com`).
O serviço tem de entregar ao email, e o ficheiro pendente tem de o
registar.

[ASSUMPTION] Se for falso — "rodar a senha do mysql" significa apenas
"mudar a senha do MySQL local para algo forte e sair do defaults":
consequência: o trabalho é menor, mas a **exposição da senha de
produção** continua, e o operador acredita que não.
Validation: o contexto é a T027 (a senha de produção foi publicada na
internet). A leitura correcta é a que trata **ambas**.

[ALTERNATIVE FRAMING] Em vez de "rodar uma senha", enquadrar como
"**a senha deixa de ser um segredo compartilhado**": cada ambiente tem a
sua, nenhuma vive no repositório, e o que o repositório guarda é a
*forma* (`DB_PASSWORD=${...:?}`) e não o *valor*.
```

### Implementor Phase

```
## Como se fosse uma criança
Cada casa tem a sua chave e a chave está no bolso de quem mora lá, não
num quadro na rua. A chave do correio fica no sitio seguro e só o
carteiro a vê.

## Como se fosse um especialista

**A. Rotação do MySQL**

(A1) `tools/rotate_db_password.py` — a ferramenta de rotação.

```python
def rotate(host, port, user, new_password, root_user, root_password):
    # 1. liga-se como root
    # 2. ALTER USER '<user>'@'%' IDENTIFIED BY '<nova>'
    # 3. VERIFICA: a senha antiga falha, a nova funciona
    # 4. escreve a nova senha num ficheiro 0600
```

A **verificação** é a parte que a torna fiável: sem ela, a rotação é
uma promessa. A ferramenta liga-se duas vezes — uma com a senha velha
(que tem de falhar) e outra com a nova (que tem de funcionar).

(A2) A rotação do MySQL **local**, aqui e agora. A senha nova é
gerada, guardada no `.env` (não versionado, 0600) e aplicada.

(A3) O `.env` passa a ser a fonte única das credenciais **locais**:
o compose lê-o automaticamente; o `Makefile` passa a incluí-lo; as
ferramentas Python passam a lê-lo.

(A4) Nenhum *default* de senha no repositório:

```yaml
- MYSQL_PASSWORD=${XKHM_DB_PASSWORD:?defina XKHM_DB_PASSWORD no .env}
```

O `:?` faz o `docker compose` **falhar** em vez de arrancar com uma
senha inventada. O erro é explícito.

(A5) A senha de produção sai de todos os ficheiros versionados. Em
`configuration.php`:

```php
$cfg['db_password'] = getenv('DB_PASSWORD') ?: '';
```

Com a verificação de que a variável existe, para que a falha seja
clara e não um "access denied" às 3 da manhã.

**B. A entrega de correio**

(B1) `SmtpTokenDelivery` sem autenticação: hoje passa sempre
`--user utilizador:senha`. Com a porta 25 e sem credenciais, isso
produz uma autenticação vazia — que a Brevo rejeita. O `--user` só é
passado quando **há** credenciais.

(B2) `CompositeTokenDelivery`: o ficheiro é **sempre** escrito; o SMTP
é um extra. É a promessa do T031, cumprida.

(B3) O correio do host (`tools/mail_pending_tokens.py`):

```
lê storage/reset-tokens/pending/*.txt
  → envia com smtplib para 127.0.0.1:25 (sem auth)
  → From: safedata@linuxkafe.com
  → apaga o ficheiro se entregou
  → move para failed/ com o motivo se falhou
```

Sem `smtplib` seria um `curl` com `--upload-file`; `smtplib` é da
biblioteca padrão e fala SMTP como deve ser.

(B4) A entrega é assíncrona em relação ao pedido HTTP: o pedido grava o
ficheiro, o correio do host entrega no minuto seguinte. **Isto é
melhor**: o pedido HTTP não depende do servidor de correio, e uma
falha do correio não transforma o pedido num 500.

(B5) O token do admin é entregue ao `email` de `tbladmins`.
```

## Addressing FAILURE MODE 1 (confundir as senhas)

Mitigation: a rotação local e a rotação de produção são **acções
distintas**, com resultados distintos, e o relatório diz qual delas foi
executada. A ferramenta de produção fica escrita, testada e pronta; a
execução depende do acesso ao `10.0.3.1`.

## Addressing FAILURE MODE 2 (healthcheck com a senha errada)

Mitigation: o healthcheck passa a ler a **variável de ambiente** e a
usar `MYSQL_PWD` (a senha não fica na linha de comando, logo não
aparece no `ps`).

## Addressing FAILURE MODE 3 (o contentor não chega ao host)

Mitigation: a entrega é feita por um processo **no host**, com o
`crontab` do utilizador (verificado a correr). `network_mode: host`
ficou de fora — a porta 80 está ocupada e partilhar a pilha de rede do
host traria de volta a colisão da T027.

## Addressing FAILURE MODE 4 (a promessa violada)

Mitigation: `CompositeTokenDelivery` — o ficheiro é sempre escrito, o
SMTP é o extra. E a regra de posse fica explícita: **quem tem SMTP
entrega; quem não tem, o ficheiro fica e o correio do host entrega.**

## Addressing FAILURE MODE 5 (o admin não tem email de entrega)

Mitigation: `tbladmins.email` é a origem do endereço de entrega; o
ficheiro pendente regista o `account` para contexto.

## [SOLUTION PROPOSAL]

Stage 1 — as credenciais: `.env`, rotação local, defaults fora.
Stage 2 — a limpeza da senha de produção nos ficheiros versionados.
Stage 3 — a ferramenta de rotação (com verificação).
Stage 4 — o SMTP: sem autenticação, composto, o correio do host.
Stage 5 — os testes.

## Technical Approach

**Chosen:** cada ambiente tem as suas credenciais, guardadas fora do
repositório, com uma forma (`${VAR:?}`) no repositório em vez de um
valor; e a entrega do correio é feita pelo host, que é onde o servidor
de correio está.

**What will NOT be implemented:** reescrever o histórico do git; mexer
na configuração do Postfix ou na Brevo; qualquer coisa que exija `sudo`.

### Affected files

| File | Operation | Description |
|------|-----------|-------------|
| `tools/rotate_db_password.py` | create | A rotação, com verificação |
| `tools/setup_local_env.py` | create | Gera o `.env` local com senhas fortes |
| `tools/mail_pending_tokens.py` | create | O correio do host |
| `tools/load_env.py` | create | O leitor de `.env`, partilhado pelas ferramentas |
| `src/Service/CompositeTokenDelivery.php` | create | Ficheiro sempre + SMTP |
| `src/Service/SmtpTokenDelivery.php` | modify | SMTP sem autenticação |
| `src/Service/AdminPasswordResetService.php` | modify | Entrega ao email do admin |
| `src/Service/FileTokenDelivery.php` | modify | Regista o `account` |
| `public/index.php` | modify | A entrega composta |
| `configuration.php` | modify | A senha de produção sai |
| `docker-compose.yml` | modify | Defaults fora; healthcheck correcto |
| `Makefile` | modify | Carrega o `.env`; sem defaults |
| `.env.production` | modify | Template, sem segredos |
| `tools/*.py` | modify | Lêem o `.env` |
| `docs/DATABASE.md` | modify | A rotação e o correio |
| `tests/Integration/TokenDeliveryTest.php` | create | As regras da entrega |

## Testing Strategy

- **As regras:** o ficheiro é sempre escrito; o SMTP sem auth não leva
  `--user`; o correio do host entrega e apaga; uma falha move para
  `failed/` e não se perde nada.
- **Ponta a ponta:** pedir a recuperação → o ficheiro pendente aparece
  com o destinatário certo → o correio do host entrega (contra um
  servidor SMTP falso) → o ficheiro desaparece → o token funciona.

## Verification Criteria

- [ ] AC1 a AC10 evidenciados
- [ ] `grep -rn "qm9W8amh5XV9A4Dr"` não devolve nada em ficheiros versionados
- [ ] `make test`, `make lint`, `make e2e`, `make flutter-test` verdes

## Estimation

- Complexity: high (> 8h)
- Risk: medium — mexe em credenciais; o caminho de reversão é o `.env`
  anterior e o `docker compose down` do volume
- Blocking dependencies: none
