<?php

$cfg = array();

$cfg['db_host'] = 'mysql';
$cfg['db_username'] = 'linuxkonect';
// The environment variable wins, so the local Docker stack can use its own
// database password. The value below is the PRODUCTION one and is only used
// when no environment variable is set.
//
// It is also in the git history — it must be rotated. See
// aes/tickets/T027.md.
$cfg['db_password'] = getenv('DB_PASSWORD') ?: 'qm9W8amh5XV9A4Dr';
$cfg['db_name'] = 'linuxkonect';
$cfg['db_port'] = '3306';
$cfg['mysql_charset'] = 'utf8mb4';

$cfg['cc_encryption_hash'] = 'fu6RHD3GlqaSEOPeuK1nuxSG82puHHmN2iuAMo6YF7G9Lme5X5jht1NrU3LsBX5t';

$cfg['templates_compiledir'] = 'templates_c/';
$cfg['customadminpath'] = 'admin';
$cfg['license'] = '';
$cfg['display_errors'] = false;

$cfg['session_name'] = 'XKHM';
$cfg['session_lifetime'] = 120;
$cfg['session_secure'] = false;
$cfg['session_httponly'] = true;

$cfg['gateway_accounts'] = array();

$cfg['system_url'] = 'http://localhost:8083';
$cfg['admin_path'] = 'admin';