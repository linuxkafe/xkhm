<?php

declare(strict_types=1);

namespace Xkhm\Module\Server;

/**
 * Factory for server provisioning modules.
 */
final class ServerModuleFactory
{
    /**
     * Create a module instance based on the server type.
     *
     * @param string $type  The module type (cpanel, virtualmin, etc.)
     * @param array  $config Server configuration (hostname, username, password, etc.)
     * @return ServerModuleInterface|null
     */
    public static function create(string $type, array $config): ?ServerModuleInterface
    {
        return match (strtolower(trim($type))) {
            'cpanel' => new Cpanel($config),
            'virtualmin' => new Virtualmin($config),
            default => null,
        };
    }

    /**
     * List of supported module types.
     *
     * @return list<string>
     */
    public static function supported(): array
    {
        return ['cpanel', 'virtualmin'];
    }
}