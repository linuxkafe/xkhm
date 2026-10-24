<?php

declare(strict_types=1);

namespace Xkhm\Tests\Integration;

use PHPUnit\Framework\TestCase;

/**
 * Basic integration test for the WordPress plugin skeleton.
 */
final class WordPressPluginTest extends TestCase
{
    private string $pluginDir;

    protected function setUp(): void
    {
        $this->pluginDir = dirname(__DIR__, 2) . '/wordpress-plugin';
    }

    public function testPluginFileExists(): void
    {
        $this->assertFileExists($this->pluginDir . '/xkhm.php');
    }

    public function testShortcodeRegistered(): void
    {
        $content = file_get_contents($this->pluginDir . '/includes/shortcodes.php');
        $this->assertStringContainsString("add_shortcode('xkhm_client_area'", $content);
    }

    public function testRewriteRulesFileExists(): void
    {
        $this->assertFileExists($this->pluginDir . '/includes/rewrite.php');
    }

    public function testAdminMenuFileExists(): void
    {
        $this->assertFileExists($this->pluginDir . '/includes/admin-menu.php');
    }

    public function testCliImportFileExists(): void
    {
        $this->assertFileExists($this->pluginDir . '/includes/cli-import.php');
    }

    public function testWpAdminMiddlewareExists(): void
    {
        $this->assertFileExists($this->pluginDir . '/includes/wp-admin-middleware.php');
    }

    public function testComposerJsonValid(): void
    {
        $composer = $this->pluginDir . '/composer.json';
        $this->assertFileExists($composer);
        $data = json_decode(file_get_contents($composer), true);
        $this->assertIsArray($data);
        $this->assertArrayHasKey('name', $data);
        $this->assertStringStartsWith('xkhm/', $data['name']);
    }

    public function testPluginIsSelfContained(): void
    {
        $bundle = dirname(__DIR__, 2) . '/build/wp-plugin';
        if (!is_dir($bundle)) {
            $this->markTestSkipped('Run make wp-plugin-build first');
        }
        $this->assertDirectoryExists($bundle . '/src');
        $this->assertDirectoryExists($bundle . '/vendor');
        $this->assertFileExists($bundle . '/vendor/autoload.php');
    }
}