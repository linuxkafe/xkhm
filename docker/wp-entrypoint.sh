#!/bin/bash
# Instala pdo_mysql se não estiver disponível
if ! php -m | grep -q pdo_mysql; then
    docker-php-ext-install pdo_mysql 2>/dev/null
fi
# Activa o plugin xkhm se estiver inactivo
if [ -f /var/www/html/wp-content/plugins/xkhm/xkhm.php ]; then
    php -r "
    require '/var/www/html/wp-load.php';
    if (!is_plugin_active('xkhm/xkhm.php')) {
        activate_plugin('xkhm/xkhm.php');
    }
    " 2>/dev/null
fi
# Executa o entrypoint original
exec docker-entrypoint.sh "$@"