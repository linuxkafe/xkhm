<?php

declare(strict_types=1);

namespace Xkhm\Tests\Integration;

use PHPUnit\Framework\TestCase;
use Xkhm\Service\SettingsLabels;

/**
 * The names of the settings, and the sections they belong to.
 *
 * The WHMCS's configuration pages are encrypted, so most of their
 * labels cannot be read — but the ones the language file does cover
 * come out in Portuguese, and the sections are the WHMCS's own
 * configuration pages.
 */
class SettingsLabelsTest extends TestCase
{
    private ?SettingsLabels $labels = null;

    protected function setUp(): void
    {
        $this->labels = new SettingsLabels();
    }

    public function testTheLabelComesFromTheLanguageFile(): void
    {
        // A etiqueta que o ficheiro de idioma do WHMCS traz.
        $this->assertSame(
            'Tipo da Multa por Atraso',
            $this->labels->label('LateFeeType')
        );

        $this->assertSame(
            'Nome da Empresa',
            $this->labels->label('CompanyName')
        );
    }

    public function testTheSettingWithoutALabelKeepsItsOwnName(): void
    {
        // `TaxEnabled` não está no ficheiro de idioma: o WHMCS escreve
        // o nome da definição, humanizado, em inglês.
        $this->assertSame('Tax Enabled', $this->labels->label('TaxEnabled'));
    }

    public function testAnUnknownSettingStillGetsAName(): void
    {
        $this->assertSame('Uma Definicao Que Nao Existe', $this->labels->label('UmaDefinicaoQueNaoExiste'));
    }

    public function testTheSettingsAreSplitIntoSections(): void
    {
        $sections = $this->labels->sections();

        $this->assertGreaterThan(10, count($sections), 'the WHMCS has many configuration pages');

        // As secções que o operador pediu: as que têm definições
        // reais, e que não são um "diversos" com quase tudo.
        foreach (['general', 'servers', 'domains', 'invoices', 'tax', 'currencies'] as $section) {
            $this->assertArrayHasKey($section, $sections);
        }
    }

    public function testEachSectionHasAName(): void
    {
        foreach (array_keys($this->labels->sections()) as $section) {
            $name = $this->labels->sectionName($section);

            $this->assertNotSame('', $name);
            $this->assertNotSame($section, $name, 'the section is shown by its name, not its key');
        }
    }

    public function testASettingLandsInTheSectionOfItsKind(): void
    {
        // Os servidores.
        $this->assertSame('servers', $this->labels->sectionOf('DefaultNameserver1'));
        // Os domínios.
        $this->assertSame('domains', $this->labels->sectionOf('DomainRegisterTLDs'));
        // As faturas.
        $this->assertSame('invoices', $this->labels->sectionOf('CreateInvoiceOnlineInvoice'));
        // A moeda.
        $this->assertSame('currencies', $this->labels->sectionOf('MainCurrency'));
        // Os impostos.
        $this->assertSame('tax', $this->labels->sectionOf('TaxEnabled'));
        // A empresa.
        $this->assertSame('general', $this->labels->sectionOf('CompanyName'));
    }

    public function testTheSectionsAreNotOneBucket(): void
    {
        // A secção maior não pode ser a maior de todas: a configuração
        // tem de estar **separada**, e não num "diversos" com 175
        // definições.
        $sections = $this->labels->sections();

        $this->assertGreaterThanOrEqual(3, count($sections));
    }

    public function testTheAliasBringsThePagesTogether(): void
    {
        // «Global» e «Home» são páginas do WHMCS, e não páginas da
        // configuração: as definições delas vivem em «Geral».
        $this->assertSame('general', $this->labels->normalise('global'));
        $this->assertSame('general', $this->labels->normalise('home'));
        $this->assertSame('tax', $this->labels->normalise('taxconfig'));
        $this->assertSame('auto', $this->labels->normalise('automation'));
    }
}
