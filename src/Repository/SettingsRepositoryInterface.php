<?php

declare(strict_types=1);

namespace Xkhm\Repository;

/**
 * The settings of the installation.
 *
 * They live in `tblconfiguration` — the table the WHMCS keeps them in,
 * and the table that came with the imported database. That makes the
 * settings **shared**: the WHMCS and this application read the same
 * ones, and a change made in one is seen by the other.
 */
interface SettingsRepositoryInterface
{
    /**
     * Every setting, as `setting => value`.
     *
     * @return array<string, string>
     */
    public function all(): array;

    /**
     * The value of a setting, or null.
     */
    public function get(string $setting): ?string;

    /**
     * Write a setting.
     *
     * The setting is **created** when it does not exist yet: the
     * WHMCS does the same, and a setting that is missing is a setting
     * that is empty.
     */
    public function set(string $setting, string $value): bool;

    /**
     * Write several settings at once.
     *
     * @param array<string, string> $values
     */
    public function setMany(array $values): bool;
}
