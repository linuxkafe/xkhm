---
id: SD-OPS-004
title: "Uma credencial versionada é uma credencial publicada"
tier: ops
epistemic_state: verified
confidence: 0.95
date: 2026-10-21
source: T032
tags: [security, secrets, configuration, operations]
---

# Uma credencial versionada é uma credencial publicada

## O que aconteceu

A senha do MySQL de produção esteve em **10 ficheiros versionados** e
em **10+ commits**:

```
.env.production        configuration.php      docker-compose.yml
CLAUDE.md              docs/ROADMAP.md         aes/shadow/SD-OPS-001.md
aes/tickets/T002-plan.md  aes/tickets/T027.md
```

E a base de dados que ela protegia esteve publicada em `0.0.0.0:3306`
durante um período (ver [[SD-OPS-001]]). Um segredo num ficheiro
versionado não está "escondido" — está **publicado**, com a
persistência de um backup e a audibilidade de um log de commits.

## A regra

> No repositório fica a **forma** da configuração, nunca o **valor**.

```yaml
# antes
- MYSQL_PASSWORD=${XKHM_DB_PASSWORD:-xkhm-local-only}

# depois
- MYSQL_PASSWORD=${XKHM_DB_PASSWORD:?defina XKHM_DB_PASSWORD no .env (make db-env)}
```

A diferença não é cosmética:

- o `:-` dava um **default** — o compose arrancava com uma senha
  inventada, e ninguém notava;
- o `:?` faz o `docker compose` **recusar arrancar**, com uma mensagem
  que diz o que fazer.

E o valor vive num `.env` **não versionado**, com modo `0600`.

## Os três passos

1. **Tirar dos ficheiros** — a partir de agora, o valor só está no
   ambiente.
2. **Rodar** — é isto que *neutraliza* a exposição: a senha velha
   deixa de servir, mesmo que continue no histórico.
3. **Reescrever o histórico** — é isto que a *remove*. É destrutivo
   (afecta todos os clones) e por isso é uma decisão do operador, não
   uma decisão automática.

Trocar o passo 2 pelo 3, ou o 3 pelo 2, são erros diferentes. O passo
1 sem o 2 não protege nada.

## A rotação verifica-se

```bash
python3 tools/rotate_db_password.py --host <host> --user <user> --apply
```

```
  a senha antiga ainda funciona? não
  a senha nova funciona?         sim
  [OK]    senha rodada e verificada.
```

Uma rotação que não é verificada é uma promessa. A verificação é uma
segunda ligação com a senha antiga (que tem de **falhar**) e uma
terceira com a nova (que tem de **funcionar**).

## E os segredos que não se podem rodar

O `CC_ENCRYPTION_HASH` é **partilhado** com o WHMCS: rodá-lo tornaria
ilegíveis os dados encriptados na base. Saiu do repositório na mesma
— porque um segredo partilhado num ficheiro versionado é um segredo
partilhado e publicado — mas a sua rotação é uma **migração**, não um
`ALTER USER`.
