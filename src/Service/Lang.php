<?php

declare(strict_types=1);

namespace Xkhm\Service;

/**
 * The language of the administration panel.
 *
 * The panel is in Portuguese by default. The operator can switch to
 * English with the toggle in the header; the choice is stored in a
 * cookie and survives the session.
 *
 * Only the **chrome** of the panel is translated here (navigation,
 * buttons, common labels). The settings' labels come from the
 * WHMCS's own language file, which is Portuguese-only in this
 * installation.
 */
final class Lang
{
    private const COOKIE = 'xkhm_lang';

    /** @var array<string, array<string, string>> */
    private const STRINGS = [
        'pt' => [
            'dashboard' => 'Painel',
            'clients' => 'Clientes',
            'products' => 'Produtos',
            'servers' => 'Servidores',
            'invoices' => 'Faturas',
            'orders' => 'Pedidos',
            'tickets' => 'Tickets',
            'domains' => 'Domínios',
            'affiliates' => 'Afiliados',
            'search' => 'Pesquisar',
            'config' => 'Configuração',
            'logout' => 'Terminar sessão',
            'admin' => 'Administração',
            'new' => 'Novo',
            'save' => 'Guardar',
            'cancel' => 'Cancelar',
            'delete' => 'Remover',
            'edit' => 'Editar',
            'filter' => 'Filtrar',
            'search_placeholder' => 'Pesquisar',
            'no_results' => 'Nenhum resultado',
            'loading' => 'A carregar…',
            'error' => 'Ocorreu um erro.',
            'success' => 'Guardado.',
        ],
        'en' => [
            'dashboard' => 'Dashboard',
            'clients' => 'Clients',
            'products' => 'Products',
            'servers' => 'Servers',
            'invoices' => 'Invoices',
            'orders' => 'Orders',
            'tickets' => 'Tickets',
            'domains' => 'Domains',
            'affiliates' => 'Affiliates',
            'search' => 'Search',
            'config' => 'Configuration',
            'logout' => 'Sign out',
            'admin' => 'Administration',
            'new' => 'New',
            'save' => 'Save',
            'cancel' => 'Cancel',
            'delete' => 'Remove',
            'edit' => 'Edit',
            'filter' => 'Filter',
            'search_placeholder' => 'Search',
            'no_results' => 'No results',
            'loading' => 'Loading…',
            'error' => 'An error occurred.',
            'success' => 'Saved.',
        ],
    ];

    /**
     * The current language code.
     */
    public static function code(): string
    {
        // O parametro da URL tem prioridade: e' a escolha do operador
        // neste pedido.
        $lang = $_GET['lang'] ?? $_COOKIE[self::COOKIE] ?? 'pt';

        return in_array($lang, ['pt', 'en'], true) ? $lang : 'pt';
    }

    /**
     * A translated string.
     */
    public static function t(string $key): string
    {
        $code = self::code();

        return self::STRINGS[$code][$key] ?? self::STRINGS['pt'][$key] ?? $key;
    }

    /**
     * Set the language (via cookie).
     */
    public static function set(string $code): void
    {
        if (in_array($code, ['pt', 'en'], true)) {
            setcookie(self::COOKIE, $code, time() + 365 * 24 * 3600, '/');
        }
    }
}
