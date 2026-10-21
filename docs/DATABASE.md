# A base de dados

Este documento explica, sem rodeios, **o estado da base de dados** e **como
pôr a aplicação a trabalhar contra os dados reais**.

---

## O estado, em uma linha

> A base de dados local `linuxkonect` é um **fixture de desenvolvimento**
> (16 tabelas, dados de exemplo). A base de dados de **produção**
> (`10.0.3.1/linuxkonect`) **nunca foi importada** — porque é inalcançável
> a partir da máquina onde o xkhm corre.

---

## Porquê é que o login falha

Não é a senha. São três coisas, por ordem:

### 1. A base de dados de produção não é alcançável

```
$ ip route get 10.0.3.1
10.0.3.1 via 10.0.0.1 dev eno1 src 10.0.0.111

$ ip route
default via 10.0.0.1 dev eno1
10.0.0.0/24 dev eno1              ← a sub-rede local é 10.0.0.0/24
                                   ← NÃO há rota para 10.0.3.0/24

$ traceroute 10.0.3.1
1  10.0.0.1
2  192.168.1.1                    ← o router também não conhece 10.0.3.0/24
3  37.28.216.2                    ← o pacote segue para a Internet
```

A máquina está em `10.0.0.111/24`. O servidor de base de dados está numa
rede `10.0.3.0/24` que **o gateway por omissão desconhece**. O pacote sai
para a Internet e morre.

**Isto não é firewall, não é credencial, não é a aplicação.** É ausência de
rota. O mesmo bloqueio já estava documentado no projecto whmcs
(`whmcs/aes/tickets/T002-dbfunctions.md`: *"live DB `10.0.3.1:3306`
unreachable from build host"*).

### 2. Não existe dump em lado nenhum

```
$ find /home/seyon/dev -name '*.sql'
/home/seyon/dev/xkhm/docker/init.sql      ← o fixture, não a produção
/home/seyon/dev/pHantasma/tests/schema.sql ← outro projecto
```

A base `whmcs` do contentor `whmcs-mysql` está **vazia** (sem tabelas).

### 3. O utilizador `linuxkafe` não existe localmente

Porque a linha real nunca foi importada. O `linuxkafe@linuxkafe.com` que
existe aqui foi **criado pelo fixture**, para que a suíte de testes tenha uma
conta com que trabalhar.

### Diagnóstico num comando

```bash
make db-check
```

```
1. A base de dados de produção está acessível a partir daqui?
  [FALHA] 10.0.3.1:3306 — a ligação expirou (tempo limite)
          → a base de dados de produção não é alcançável a partir desta
            máquina. Isto NÃO é um problema de credenciais.
  [FALHA] rota de rede
          → adicione uma rota estática para a sub-rede do servidor, ou corra
            a exportação numa máquina que a alcance

2. Há algum dump da base de dados de produção disponível localmente?
  [FALHA] nenhum dump encontrado
          → corra `make db-export` numa máquina que alcance 10.0.3.1

3. A base de dados local tem o esquema esperado?
  [OK]    base local — 16 tabelas em `linuxkonect`
          Estas são as tabelas do FIXTURE DE DESENVOLVIMENTO, não os dados
          de produção.
```

---

## Como pôr os dados reais

### Opção A — o dump (funciona sempre)

**1. Numa máquina que alcance `10.0.3.1`** (o próprio servidor, ou um
computador na rede dele):

```bash
cd /home/seyon/dev/xkhm
make db-export
# → docker/dumps/linuxkonect-20260721-120000.sql
```

Ou, se o `mysqldump` não estiver instalado:

```bash
mysqldump -h 10.0.3.1 -u linuxkonect -p'...' \
  --single-transaction --routines --triggers --events \
  --default-character-set=utf8mb4 --no-tablespaces \
  linuxkonect > linuxkonect-$(date +%Y%m%d).sql
```

> ⚠️ **O dump contém dados pessoais** (nomes, emails, moradas, IPs). Não o
> versione, não o envie por email e apague-o quando já não for preciso.

**2. Traga o ficheiro para a máquina do xkhm** (por `scp`, ou por qualquer
meio seguro).

**3. Importe:**

```bash
make db-import FILE=docker/dumps/linuxkonect-20260721.sql
```

O import:
1. faz uma cópia de segurança da base local (`docker/backups/`);
2. recria o esquema e aplica o dump — **o dump traz os seus próprios
   `CREATE TABLE`**, portanto o esquema real é aplicado tal e qual e nada é
   inventado;
3. **verifica**: quantas tabelas foram importadas, se as 16 que a aplicação
   usa existem, e quantas linhas há em cada uma.

**4. Verifique:**

```bash
make test        # o SchemaParityTest corre contra os dados reais
make e2e         # a suíte E2E contra os dados importados
```

### Opção B — uma rota de rede (se a máquina ficar atrás do router certo)

Se `10.0.0.1` **tiver** rota para `10.0.3.0/24` (é uma questão de
configurar o router), basta:

```bash
sudo ip route add 10.0.3.0/24 via 10.0.0.1
make db-check    # volta a confirmar a conectividade
```

Se o router **não** tiver essa rota, a Opção A é a via.

### Opção C — réplicação

`mysqldump` de vez em quando, ou uma réplica MySQL. A Opção A é mais
simples e não exige configuração no servidor de produção.

---

## O que a aplicação faz com a base de dados

A aplicação (`xkhm`) é **diretamente compatível** com o esquema WHMCS 5.3.9:
todas as consultas são sobre as tabelas `tbl*` e usam os nomes de coluna do
WHMCS. Não há tabelas próprias, nem colunas próprias, nem uma camada de
tradução.

Isto é garantido por `tests/Integration/SchemaParityTest.php`, que executa
**todas** as consultas dos repositórios contra a base de dados e afirma que
cada coluna referenciada existe.

### As 16 tabelas que a aplicação usa

`tbladmins`, `tblaffiliates`, `tblaffiliatesclients`, `tblaffiliatespayouts`,
`tblannouncements`, `tblclients`, `tbldomainpricing`, `tbldomains`,
`tblinvoiceitems`, `tblinvoices`, `tblknowledgebase`, `tblknowledgebasecats`,
`tblorders`, `tblticketdepartments`, `tblticketreplies`, `tbltickets`

As restantes tabelas do WHMCS (produtos, alojamentos, servidores, contas…)
são **preservadas** na importação mas ainda não são usadas pela aplicação.

---

## Comandos

| Comando | O que faz |
|---------|-----------|
| `make db-check` | Diagnostica porque é que o login falha |
| `make db-export` | Exporta a base de produção (noutra máquina) |
| `make db-import FILE=…` | Importa um dump e verifica o resultado |
| `make db-backup` | Cópia de segurança da base local |
| `make db-seed` | Volta ao fixture de desenvolvimento |

---

## As credenciais

| Conta | Onde | Password |
|-------|------|----------|
| `admin` | `tbladmins` | a que estiver na base importada |
| `linuxkafe@linuxkafe.com` | `tblclients` | a que estiver na base importada |
| `admin` / `admin123` | fixture local | `admin123` |
| `linuxkafe@linuxkafe.com` / `linuxkafe123` | fixture local | `linuxkafe123` |

Depois de importar a base real, **as senhas são as de lá** — as do fixture
desaparecem porque as tabelas são recriadas.
