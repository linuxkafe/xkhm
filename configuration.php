<?php

$cfg = array();

$cfg['db_host'] = 'mysql';
$cfg['db_username'] = 'linuxkonect';

// The password comes from the environment. There is **no fallback**.
//
// A password in this file is a password in the git history, and this
// one was: it was published on 0.0.0.0:3306 with the production
// database (see aes/tickets/T027.md). The environment is the only place
// a credential belongs.
//
// The file does not stop the process when the variable is missing:
// `Config` raises the error, at the point where a connection is really
// needed, with a message that says what to do.
$cfg['db_password'] = (string)(getenv('DB_PASSWORD') ?: '');
$cfg['db_name'] = 'linuxkonect';
$cfg['db_port'] = '3306';
$cfg['mysql_charset'] = 'utf8mb4';

// The WHMCS encryption hash. It is a **shared** secret: the application
// and the WHMCS must agree on it, or the data the WHMCS encrypted in
// the database becomes unreadable.
//
// It comes from the environment for the same reason as the password, but
// it cannot simply be rotated: doing so needs a migration of the
// encrypted data. It is listed here as a known debt.
$cfg['cc_encryption_hash'] = (string)(getenv('CC_ENCRYPTION_HASH') ?: '');

$cfg['templates_compiledir'] = 'templates_c/';
$cfg['customadminpath'] = 'admin';
$cfg['license'] = '';
$cfg['display_errors'] = true;

$cfg['session_name'] = 'XKHM';
$cfg['session_lifetime'] = 120;
$cfg['session_secure'] = false;
$cfg['session_httponly'] = true;

$cfg['gateway_accounts'] = array();

$cfg['system_url'] = 'http://localhost:8083';
$cfg['admin_path'] = 'admin';
