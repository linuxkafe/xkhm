---
ticket: T031
phase: learn
status: done
created: 2026-10-21
requires:
  - aes/tickets/T031-verify.md
---

# T031 — Learn: Sistema de Recuperação de Credenciais

## O que se aprendeu

### 1. A ordem das dependências em `public/index.php` é código

O PHP não tem injecção de dependências; o ficheiro de arranque **é** o
grafo de dependências, e a ordem das linhas é semântica.

Três vezes seguidas o mesmo erro apareceu: um serviço usado por um
controlador, criado **depois** dele. O sintoma — `Undefined variable` —
não aponta para a causa, e a classe de erro (`Error` e não `Exception`)
faz o `try/catch` de costume não apanhar nada.

**Aprendido:** quando se acrescenta um serviço a um ficheiro de arranque
manual, a *primeira* verificação a fazer é `grep -n "new " public/index.php`
e ler a ordem. O sintaxe-check não apanha isto — o PHP só falha em
execução.

### 2. `docker compose up -d` não reconstrói a imagem

Três vezes seguidas, uma alteração "não fez nada": o contentor servia o
código antigo, o novo endpoint respondia, mas o serviço novo nem estava
lá. `class_exists()` no contentor foi o que denunciou.

**Aprendido:** depois de tocar no código, é `docker compose build
--no-cache && docker compose up -d`. Sem isso, o debug passa a Investigar
código que não está a correr — a pior forma de perder tempo.

### 3. PDO sem emulação não reutiliza parâmetros nomeados

`... SET password = :hash, passwordhash = :hash ...` com
`ATTR_EMULATE_PREPARES => false` dá `SQLSTATE[HY093]`. Com emulação, o
MySQL aceita; sem ela, o PDO converte cada nome numa posição e a segunda
ocorrência é uma posição a mais.

**Aprendido:** um placeholder nomeado **é** uma posição. Usá-lo duas
vezes são duas posições. Escrever `:hash` e `:hash2` com o mesmo valor
é a forma correcta — e mais explícita.

### 4. O contentor e o host partilham o sistema de ficheiros, não as permissões

O directório montado (`./storage`) pertence ao utilizador do host
(uid 1004, modo 0700). O PHP corre como `www-data` (uid 33) e **não**
consegue escrever lá. O `chmod` do `Dockerfile` não ajuda: o volume
montado **tapa** o directório da imagem.

**Aprendido:** um volume montado herda as permissões do host, não as da
imagem. O token não aparecia porque o processo **não conseguia** gravá-lo —
e o código reportava sucesso, porque o `file_put_contents` falhava com um
`@` a silenciar o erro.

**Aprendido (o mais importante):** um `@` à frente de uma escrita a disco
transforma um erro em silêncio. Um sistema de recuperação que reporta
sucesso sem gravar nada é **pior** do que não existir: o operador acredita
que tem um caminho e não tem.

### 5. Um token de uso único não pode ser usado para restaurar o estado

O teste E2E da recuperação acabava por "restaurar" a senha original com o
mesmo token — e falhava, porque o token tinha sido **consumido**. A
solução não foi afrouxar a regra: foi dar à suíte as suas **próprias
contas** (`xkhm-reset@example.com`, `xkhm-reset-admin`), cujas senhas
podem mudar à vontade.

**Aprendido:** quando um teste altera um estado, ou o teste tem o seu
próprio estado, ou o estado é restaurável. Partilhar o estado com os
outros testes e tentar limpá-lo no fim é uma fonte de falhas que não
mexe no código sob teste.

### 6. Reescrever um controlador à pressa apaga métodos

A reescrita do `AdminAuthController` (para acrescentar o formulário de
recuperação) esqueceu-se do método `logout()`. O resultado foi um 500
num endpoint que funcionava antes.

**Aprendido:** o `git diff` mostra linhas acrescentadas; **não mostra as
que desapareceram**, se o ficheiro foi reescrito por inteiro. Rever
`git diff --stat` e procurar por `->method` removidos, ou rever o diff
por *função*. Um 500 num endpoint que funcionava antes é, quase sempre,
uma função que se perdeu.

### 7. "Sem SMTP" não é "sem entrega"

A pergunta do operador era "recupera a senha". A resposta honesta não
é "não há servidor de correio, logo não há recuperação" — é "a entrega
tem de funcionar sem servidor de correio".

A solução foi uma **entrega plugável** com uma garantia: o ficheiro é
**sempre** escrito, mesmo quando o SMTP existe e funciona. Assim há
sempre uma via, e o e-mail é um extra, não a condição.

**Aprendido:** uma funcionalidade que promete uma coisa que não pode
entregar é pior do que a ausência da funcionalidade. A pergunta certa
não é "posso enviar o e-mail?" mas "**há sempre um caminho para dentro?**".

## Decisões que merecem ser lembradas

| Decisão | Porquê |
|---------|--------|
| O token do cliente vai para `tblclients.pwresetkey` | A coluna já existe no WHMCS 5.3.9 — foi posta lá para isto. Usá-la é falar a língua do sistema original. |
| O token do admin vai para um ficheiro | `tbladmins` não tem colunas de reset e o esquema de produção não muda. Um ficheiro expira, revoga-se e é auditável. |
| Duas implementações, um contrato | O código da aplicação não sabe qual das duas está por baixo. `requestReset` / `resetWithToken` em ambas. |
| A resposta é sempre igual | Um endpoint que responde diferente é uma oracle de contas. A diferença existe — só não é mostrada. |
| O token é apagado ao ser usado | Um token que sobrevive ao próprio uso é uma porta das trase que fica na base de dados. |
| TTL de 1 hora | A senha muda-se em minutos. Uma hora chega e a exposição é menor. |
| `developmentToken` só em `APP_ENV=development` | Em produção a resposta é genérica. O modo de desenvolvimento é o que torna o fluxo testável sem correio. |

## O que fica para depois

- **SMTP real**: `XKHM_SMTP_HOST`/`USER`/`PASS`/`FROM` — a entrega por
  e-mail activa-se sozinha quando as variáveis existem.
- **A entrega do token ao cliente continua a ser do administrador**: sem
  SMTP, é o operador que lê o ficheiro e entrega o código. É honesto, mas
  é manual.
- **T030** — a senha de produção do `linuxkafe` em `xk.linuxkafe.com` só
  o WHMCS a repõe. O fluxo novo está pronto para quando o servidor de
  correio existir.
