<?php

declare(strict_types=1);

namespace Xkhm\Tests\Integration;

use PHPUnit\Framework\TestCase;
use Xkhm\Repository\SettingsRepositoryPDO;
use Xkhm\Service\SettingsService;

/**
 * The settings of the installation.
 *
 * They live in `tblconfiguration` — the table the WHMCS keeps them in,
 * and the one that came with the imported database.
 */
class SettingsServiceTest extends TestCase
{
    private ?SettingsRepositoryPDO $repository = null;
    private ?SettingsService $settings = null;

    protected function setUp(): void
    {
        try {
            $this->repository = new SettingsRepositoryPDO();
            $this->settings = new SettingsService($this->repository);
        } catch (\Throwable $e) {
            $this->markTestSkipped('Database not available: ' . $e->getMessage());
        }
    }

    public function testTheSettingsComeFromTheTable(): void
    {
        $all = $this->settings->all();

        $this->assertGreaterThan(200, count($all), 'the WHMCS had 235 settings');
        $this->assertArrayHasKey('CompanyName', $all);
        $this->assertSame('LINUXKAFE', $all['CompanyName']);
    }

    public function testASettingIsRead(): void
    {
        $this->assertSame('LINUXKAFE', $this->settings->get('CompanyName'));
        $this->assertNull($this->settings->get('UmaDefinicaoQueNaoExiste'));
        $this->assertSame('predefinido', $this->settings->get('UmaDefinicaoQueNaoExiste', 'predefinido'));
    }

    public function testTheOnOffSettingsAreBooleans(): void
    {
        $this->assertTrue($this->settings->isOn('TaxDomains'));
        $this->assertFalse($this->settings->isOn('TaxEnabled'));
        // A default quando a definicao nao existe.
        $this->assertTrue($this->settings->isOn('UmaDefinicaoQueNaoExiste', true));
    }

    public function testTheCompanyFallsBackToTheRegistrar(): void
    {
        // A morada da empresa está vazia em `Company*`, e preenchida em
        // `RegistrarAdmin*` — que é de onde vem, na instalação real.
        $company = $this->settings->company();

        $this->assertSame('LINUXKAFE', $company['name']);
        $this->assertNotSame('', $company['address'], 'the address comes from the registrar');
        $this->assertNotSame('', $company['city']);
        $this->assertNotSame('', $company['postcode']);
        $this->assertNotSame('', $company['email']);
    }

    public function testTheMissingReportSaysWhatIsMissing(): void
    {
        $missing = $this->settings->missing();

        // O relatório é derivado do **estado actual**: uma definição
        // que tem valor não é reportada. O NIF já vem na instalação
        // importada, e o que interessa é a regra, não uma fotografia
        // dos dados.
        $all = $this->settings->all();

        foreach ($missing as $item) {
            $this->assertNotSame('', $item['why'], 'each missing setting says what it blocks');
            $this->assertNotSame('', $item['label'], 'each missing setting has a name');
        }

        // E o inverso: uma definição obrigatória que **tem** valor não
        // aparece no relatório.
        $keys = array_column($missing, 'key');

        if (trim((string)($all['CompanyTaxNumber'] ?? '')) !== '') {
            $this->assertNotContains('CompanyTaxNumber', $keys);
        } else {
            $this->assertContains('CompanyTaxNumber', $keys);
        }
    }

    public function testTheGroupsCoverEverySetting(): void
    {
        $all = $this->settings->all();
        $grouped = $this->settings->grouped();

        $counted = 0;
        foreach ($grouped as $group) {
            $counted += count($group['settings']);
        }

        $this->assertSame(
            count($all),
            $counted,
            'every setting belongs to a group'
        );
    }

    public function testAWriteReplacesTheSettingAndLeavesOneRow(): void
    {
        $original = (string)($this->settings->all()['CompanyName'] ?? '');

        try {
            $this->assertTrue($this->settings->set('CompanyName', 'NOME DE TESTE'));

            $fresh = new SettingsService(new SettingsRepositoryPDO());
            $this->assertSame('NOME DE TESTE', $fresh->get('CompanyName'));

            // A tabela não tem chave única: a escrita tem de deixar
            // **uma** linha, e não duplicar.
            $rows = $this->countRows('CompanyName');
            $this->assertSame(1, $rows);
        } finally {
            $this->settings->set('CompanyName', $original);
        }
    }

    public function testOnlyTheEditableSettingsCanBeWritten(): void
    {
        // A lista é explícita: uma definição que não está nela não é
        // editável pelo painel.
        $this->assertContains('CompanyName', SettingsService::EDITABLE);
        $this->assertContains('SequentialInvoiceNumberFormat', SettingsService::EDITABLE);

        // E as que podem invalidar a instalação não estão.
        $this->assertNotContains('InstanceID', SettingsService::EDITABLE);
        $this->assertNotContains('License', SettingsService::EDITABLE);
        $this->assertNotContains('token_namespace', SettingsService::EDITABLE);
    }

    private function countRows(string $setting): int
    {
        $config = new \Xkhm\Config();
        $pdo = \Xkhm\Database\Connection::get($config->getDbConfig());

        $stmt = $pdo->prepare(
            'SELECT COUNT(*) FROM tblconfiguration WHERE setting = ?'
        );
        $stmt->execute([$setting]);

        return (int)$stmt->fetchColumn();
    }
}
