<?php

declare(strict_types=1);

namespace Xkhm\Service;

/**
 * The **real names** of the settings, and the sections they belong to.
 *
 * The WHMCS shows the configuration with the labels of its own admin
 * language file, and it splits it into **sections** — the configuration
 * pages (`admin/config<name>.php`).
 *
 * The labels are extracted from the original installation's
 * `admin/lang/portugues.php` by `tools/extract_whmcs_labels.py`, and
 * the sections are the WHMCS's own pages.
 *
 * The settings the language file does **not** cover — most of them —
 * are hardcoded in the encrypted configuration pages, in English. Their
 * name is therefore the setting's own name, humanised the way the
 * WHMCS writes it: `LateFeeType` → «Late Fee Type».
 */
class SettingsLabels
{
    /** @var array<string, string>|null */
    private ?array $sections = null;

    /** @var array<string, string>|null */
    private ?array $labels = null;

    /**
     * The sections of the configuration, and their names.
     *
     * @return array<string, string>
     */
    public function sections(): array
    {
        if ($this->sections === null) {
            $this->sections = $this->data()['sections'] ?? [];
        }

        return $this->sections;
    }

    /**
     * The name of a section, in the language.
     */
    public function sectionName(string $section): string
    {
        $section = $this->normalise($section);

        return $this->sections()[$section] ?? $this->humanise($section);
    }

    /**
     * The section as the panel knows it.
     *
     * The sections of the language file are the WHMCS's admin pages,
     * which are not the same as its configuration pages. The alias
     * brings them to the same place — otherwise the same configuration
     * page would appear twice.
     */
    public function normalise(string $section): string
    {
        return self::SECTION_ALIASES[$section] ?? $section;
    }

    /**
     * The real name of a setting.
     */
    public function label(string $setting): string
    {
        if ($this->labels === null) {
            $this->labels = $this->data()['labels'] ?? [];
        }

        $key = strtolower($setting);

        if (isset($this->labels[$key])) {
            return $this->labels[$key];
        }

        return $this->humanise($setting);
    }

    /**
     * The section a setting belongs to.
     *
     * The WHMCS's own: the section where the language file puts the
     * setting's label. The settings the language file does not cover
     * are placed by their name — the company in «Geral», the servers in
     * «Servidores», the e-mail in «Modelos de e-mail», and assim por
     * diante.
     */
    public function sectionOf(string $setting): string
    {
        if ($this->labels === null) {
            $this->labels = $this->data()['labels'] ?? [];
        }

        $key = strtolower($setting);

        // Onde o ficheiro de idioma põe a etiqueta.
        foreach ($this->data()['label_sections'] ?? [] as $label => $section) {
            if ($label === $key) {
                return $this->normalise($section);
            }
        }

        return $this->sectionByName($setting);
    }

    /**
     * The sections of the *language file* are the WHMCS's admin pages,
     * which are not the same as its configuration pages. This maps the
     * ones that differ.
     *
     * @var array<string, string>
     */
    private const SECTION_ALIASES = [
        'global' => 'general',
        'home' => 'general',
        // O bloco «fields» do ficheiro de idioma são os **dados da
        // empresa** — que vivem na página geral do WHMCS.
        'fields' => 'general',
        'mergefields' => 'emailtemplates',
        'emailtpls' => 'emailtemplates',
        'automation' => 'auto',
        'taxconfig' => 'tax',
        'clientsummary' => 'clients',
        'services' => 'products',
        'orderstatuses' => 'orders',
        'ticketstatuses' => 'tickets',
        'configservers' => 'servers',
    ];

    /**
     * The section of a setting, by the **prefix** of its name.
     *
     * The WHMCS's configuration pages are cifradas, e as suas etiquetas
     * não se podem ler. A secção de cada definição é, por isso, a
     * **página do WHMCS** onde essa definição vive — reconstruída a
     * partir do nome.
     *
     * A lista está escrita à mão, e por isso é auditável: quem
     * acrescentar uma definição vê onde ela vai parar.
     *
     * @var array<string, list<string>>
     */
    private const SECTION_PREFIXES = [
        'currencies' => ['MainCurrency', 'Currency'],
        'servers' => ['DefaultNameserver', 'Server'],
        'tax' => ['Tax', 'LateFee'],
        'domains' => ['Domain', 'BulkDomain', 'FreeDomain', 'AllowDomains'],
        'invoices' => [
            'CreateInvoice', 'Invoice', 'ContinuousInvoicing', 'CancelInvoice',
            'ShowCancelled', 'NoInvoiceEmail', 'Credit',
        ],
        'orders' => ['Order'],
        'tickets' => ['Ticket', 'Support'],
        'emailtemplates' => [
            'Email', 'SystemEmail', 'Mail', 'SendEmail', 'SendFirst', 'SendSecond',
            'SendThird', 'PreventEmail', 'AllowClientsToChange', 'BCC',
        ],
        'auto' => ['Auto', 'Upgrad', 'Overage'],
        'addons' => ['Addon', 'Module', 'ActiveAddon'],
        'clients' => [
            'Client', 'Clients', 'Contact', 'AllowClient', 'CloseInactive',
            'DisableClients', 'DefaultToCli',
        ],
        'clientgroups' => ['Group'],
        'affiliates' => ['Affiliate'],
        'gateways' => ['Gateway', 'AutoPayment', 'AutoRedirect'],
        'securityqs' => [
            'Security', 'RequiredPW', 'SkipFraud', 'Captcha', 'InvalidLogin',
            'LoginFailure', 'RequireLogin', 'DisableAdmin', 'AdminForce',
            'Prevent', 'Proxy', 'Allowed', 'Allow',
        ],
        'promotions' => [
            'Promo', 'Generate', 'Bulk', 'AutoProvisio', 'SequentialInvoice',
        ],
        'registrars' => ['Registrar'],
        'general' => [
            'Company', 'Default', 'Show', 'Allow', 'Enable', 'Disable',
            'Update', 'Maintenance', 'Announcement', 'Toggle', 'Network',
            'Display', 'System', 'Instance', 'License', 'Version',
            'Token', 'Hooks', 'Module', 'DateFormat', 'Charset', 'Language',
            'Template', 'Signature', 'NumRecords', 'Product', 'Run',
            'Group', 'Prorata', 'Daily', 'Attachment', 'Downloads', 'Log',
            'ProductMonth', 'BulkCheck', 'Require', 'Required', 'Skip',
            'Invalid', 'Login', 'Admin', 'Captcha', 'Banned', 'Bcc',
        ],
    ];

    /**
     * The section, by the shape of the name.
     */
    private function sectionByName(string $setting): string
    {
        foreach (self::SECTION_PREFIXES as $section => $prefixes) {
            foreach ($prefixes as $prefix) {
                if (str_starts_with($setting, $prefix)) {
                    return $section;
                }
            }
        }

        return 'miscellaneous';
    }

    /**
     * `LateFeeType` → «Late Fee Type».
     */
    private function humanise(string $name): string
    {
        // As palavras compostas com abreviaturas conhecidas.
        $known = [
            'url' => 'URL',
            'id' => 'ID',
            'ip' => 'IP',
            'ssl' => 'SSL',
            'api' => 'API',
            'dns' => 'DNS',
            'ftp' => 'FTP',
            'vat' => 'VAT',
            'tax' => 'Tax',
            'cc' => 'CC',
            'tos' => 'TOS',
            'tld' => 'TLD',
            'sms' => 'SMS',
            'whois' => 'WHOIS',
            'cpanel' => 'cPanel',
            'nameserver' => 'Nameserver',
            'nameservers' => 'Nameservers',
        ];

        $spaced = preg_replace('/([a-z0-9])([A-Z])/', '$1 $2', $name) ?? $name;
        $spaced = preg_replace('/([A-Z]+)([A-Z][a-z])/', '$1 $2', $spaced) ?? $spaced;

        $words = explode(' ', $spaced);

        foreach ($words as $index => $word) {
            $lower = strtolower($word);

            if (isset($known[$lower])) {
                $words[$index] = $known[$lower];
                continue;
            }

            $words[$index] = ucfirst($lower);
        }

        return implode(' ', $words);
    }

    /**
     * The generated data.
     *
     * @return array<string, mixed>
     */
    private function data(): array
    {
        static $data = null;

        if ($data === null) {
            $path = dirname(__DIR__, 2) . '/resources/whmcs_admin_labels.php';
            $data = is_file($path) ? (require $path) : [];
        }

        return $data;
    }
}
