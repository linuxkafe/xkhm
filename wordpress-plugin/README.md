# xkhm – Plugin WordPress

Empacota o **xkhm** (reimplementação do WHMCS 5.3.9) como um plugin WordPress
autocontido. Substitui a área de cliente WHMCS pela interface Flutter já
existente e expõe as APIs compatíveis (`/api/v1/*` e `/api.php`).

## Instalação

1. Gere o pacote autocontido:
   ```bash
   make wp-plugin-build
   ```
   O resultado fica em `build/wp-plugin/`.

2. Copie a pasta `build/wp-plugin/` para `wp-content/plugins/xkhm/` (ou instale o
   ZIP gerado).

3. Active o plugin em **Plugins → Instalados**.

4. (Opcional) Defina as constantes de base de dados em `wp-config.php`:
   ```php
   define('XKHM_DB_HOST', 'localhost');
   define('XKHM_DB_NAME', 'linuxkonect');
   define('XKHM_DB_USER', 'linuxkonect');
   define('XKHM_DB_PASSWORD', '***');
   define('XKHM_DB_PORT', 3306);
   ```
   Se não forem definidas, o plugin usa as variáveis de ambiente
   `XKHM_DB_HOST`, `XKHM_DB_NAME`, `XKHM_DB_USER`, `XKHM_DB_PASSWORD`,
   `XKHM_DB_PORT` (por defeito `localhost`/`linuxkonect`/`linuxkonect`/``/`3306`).

## Área de cliente

Use o shortcode numa página:

```
[xkhm_client_area]
```

O plugin serve os assets Flutter a partir de `assets/flutter/` e reescreve
`/app/*` para essa pasta.

## Importação WHMCS

1. Vá a **Ferramentas → Importar WHMCS**.
2. Escolha a origem: ficheiro `.sql`/`.gz` ou credenciais MySQL remotas.
3. Valide o esquema (91 tabelas WHMCS 5.3.9).
4. Inicie a importação (chunked, 5 000 linhas por lote, barra de progresso).
5. Consulte o relatório final.

### WP-CLI

```bash
wp xkhm import --source=remote --host=... --port=3306 --db=... --user=... --pass=...
wp xkhm import --source=file --file=/caminho/dump.sql
```

## Backoffice

O backoffice (`/admin/*`) só é acessível a partir de `wp-admin`: o middleware
`WpAdminAuthMiddleware` exige um utilizador WordPress com a capability
`manage_options` e redireciona para `wp-login.php` caso contrário.

## Segurança

- As credenciais de importação remota são guardadas apenas em transients
  (curta duração). Para produção com dumps remotos, recomenda-se encriptação.
- O middleware WP-admin é a única porta de entrada para o backoffice.

## Requisitos
- WordPress 6.0+
- PHP 8.2+
- MySQL/MariaDB com o esquema WHMCS 5.3.9 (91 tabelas `tbl*`).