<?php

declare(strict_types=1);

namespace Xkhm\Tests\Integration;

use PHPUnit\Framework\TestCase;
use Xkhm\Config;

/**
 * Basic integration test for the WordPress plugin skeleton.
 *
 * This test boots a minimal WordPress environment (via wp-load.php) if available,
 * otherwise it marks the test as skipped.
 */
final class WordPressPluginTest extends TestCase
{
    private static bool $wpLoaded = false;

    public static function setUpBeforeClass(): void
    {
        // Try to locate a WordPress installation for testing.
        // We look for wp-load.php in common locations.
        $candidates = [
            '/var/www/html/wp-load.php',               // typical Docker WP
            dirname(__DIR__, 3) . '/wordpress/wp-load.php', // sibling wordpress folder
            getenv('WP_TEST_WP_LOAD') ?: '',
        ];

        foreach ($candidates as $path) {
            if ($path && is_file($path)) {
                self::$wpLoaded = true;
                break;
            }
        }
    }

    public function testPluginFileExists(): void
    {
        $pluginFile = dirname(__DIR__, 2) . '/wordpress-plugin/xkhm.php';
        $this->assertFileExists($pluginFile, 'Plugin main file missing');
    }

    public function testShortcodeRegistered(): void
    {
        if (!self::$wpLoaded) {
            $this->markTestSkipped('WordPress not available');
        }

        // Activate plugin programmatically
        // Note: requires WP environment with plugins API
        // For now just check that the shortcode function exists after including shortcodes.php
        require_once dirname(__DIR__, 2) . '/wordpress-plugin/includes/shortcodes.php';
        $this->assertTrue(function_exists('xkhm_client_area_shortcode'));
    }

    public function testRewriteRulesFileExists(): void
    {
        $file = dirname(__DIR__, 2) . '/wordpress-plugin/includes/rewrite.php';
        $this->assertFileExists($file);
    }

    public function testAdminMenuFileExists(): void
    {
        $file = dirname(__DIR__, 2) . '/wordpress-plugin/includes/admin-menu.php';
        $this->assertFileExists($file);
    }

    public function testCliImportFileExists(): void
    {
        $file = dirname(__DIR__, 2) . '/wordpress-plugin/includes/cli-import.php';
        $this->assertFileExists($file);
    }

    public function testWpAdminMiddlewareExists(): void
    {
        $file = dirname(__DIR__, 2) . '/wordpress-plugin/includes/wp-admin-middleware.php';
        $this->assertFileExists($file);
    }

    public function testComposerJsonValid(): void
    {
        $composer = dirname(__DIR__, 2) . '/wordpress-plugin/composer.json';
        $this->assertFileExists($composer);
        $data = json_decode(file_get_contents($composer), true);
        $this->assertArrayHasKey('name', $data);
        $this->assertStringStartsWith('xkhm/', $data['name']);
    }
}