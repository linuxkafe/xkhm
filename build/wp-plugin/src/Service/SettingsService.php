<?php

declare(strict_types=1);

namespace Xkhm\Service;

use Xkhm\Repository\SettingsRepositoryInterface;

/**
 * The settings of the installation.
 *
 * They live in `tblconfiguration` — the table the WHMCS keeps them in.
 * That has two consequences:
 *
 * - the settings are **shared** with the WHMCS: a change made in one is
 *   seen by the other;
 * - they came with the imported database, so they describe the
 *   **real** installation, and not a guess.
 *
 * ## The company
 *
 * The WHMCS keeps the company in two places: the `Company*` settings,
 * and the `RegistrarAdmin*` ones (which the registrar uses when it
 * bills the client its own details). Here the second address is the
 * one that is filled in, so the resolution falls back to it.
 *
 * ## What is missing
 *
 * Some settings are **not** there — and the application does not invent
 * them. The report says which ones are missing and what each one
 * blocks, so that the operator knows what to fill in.
 */
class SettingsService
{
    /**
     * The settings the panel groups, with the label of each group.
     *
     * @var array<string, string>
     */
    public const GROUPS = [
        'company' => 'Empresa',
        'client' => 'Registo do cliente',
        'tax' => 'IVA',
        'currency' => 'Moeda',
        'billing' => 'Facturação',
        'numbering' => 'Numeração',
        'email' => 'Correio',
        'payment' => 'Pagamento',
        'other' => 'Outras definições',
    ];

    /**
     * The settings the panel lets the operator change.
     *
     * The list is **explicit** on purpose: a setting that is not on it
     * is read-only, and the exclusion is auditable. `InstanceID`,
     * `License` and `token_namespace` are in this list's *exclusion*:
     * changing them can invalidate the installation.
     *
     * @var list<string>
     */
    public const EDITABLE = [
        // A empresa
        'CompanyName',
        'CompanyAddress1',
        'CompanyAddress2',
        'CompanyCity',
        'CompanyState',
        'CompanyPostcode',
        'CompanyCountry',
        'CompanyEmail',
        'CompanyPhone',
        'CompanyTaxNumber',
        'CompanyVatNumber',
        'CompanyTermsOfService',
        'CompanyPaymentInfo',
        'CompanyBankDetails',
        // O registo do cliente
        'RegistrarAdminCompanyName',
        'RegistrarAdminAddress1',
        'RegistrarAdminAddress2',
        'RegistrarAdminCity',
        'RegistrarAdminStateProvince',
        'RegistrarAdminPostalCode',
        'RegistrarAdminCountry',
        'RegistrarAdminPhone',
        'RegistrarAdminEmailAddress',
        'DefaultNameserver1',
        'DefaultNameserver2',
        'DefaultNameserver3',
        'DefaultNameserver4',
        // O IVA
        'TaxEnabled',
        'TaxType',
        'TaxInclusiveDeduct',
        'TaxL2Compound',
        'TaxBillableItems',
        'TaxCustomInvoices',
        'TaxDomains',
        'TaxLateFee',
        'TaxLateFeeAmount',
        'LateFeeType',
        'LateFeeMinimumAmount',
        // A moeda
        'MainCurrency',
        'CurrencyAutoUpdateExchangeRates',
        // A facturação
        'CreateInvoiceDaysBefore',
        'CreateInvoiceDaysBeforeAnnually',
        'CreateInvoiceDaysBeforeBiennially',
        'CreateInvoiceDaysBeforeMonthly',
        'CreateInvoiceDaysBeforeQuarterly',
        'CreateInvoiceDaysBeforeSemiAnnually',
        'ContinuousInvoicing',
        'InvoiceIncrement',
        'InvoiceLateFeeAmount',
        'ShowCancelledInvoices',
        'CancelInvoiceOnClientChange',
        // A numeração
        'SequentialInvoiceNumbering',
        'SequentialInvoiceNumberFormat',
        'SequentialInvoiceNumberValue',
        // O correio
        'SystemEmailsFromName',
        'SystemEmailsFromEmail',
        'Email',
        'EmailGlobalHeader',
        'EmailGlobalFooter',
        'PreventEmailAccountChange',
        'AllowClientsToChangeEmail',
        'NoInvoiceEmails',
        // O pagamento
        'AutoPaymentMethods',
        'AutoRedirectoInvoice',
        'GatewayModule',
    ];

    /**
     * The settings that block a proper payment notice, and what each one
     * blocks.
     *
     * @var array<string, string>
     */
    public const REQUIRED = [
        'CompanyTaxNumber' => 'obrigatório num documento fiscal — sem NIF não há fatura',
        'CompanyAddress1' => 'a morada do emissor',
        'CompanyCity' => 'a localidade do emissor',
        'CompanyPostcode' => 'o código postal do emissor',
        'CompanyCountry' => 'o país do emissor',
        'CompanyEmail' => 'o email de onde sai o aviso',
        'CompanyBankDetails' => 'os dados bancários, para o cliente pagar por transferência',
        'CompanyPaymentInfo' => 'a informação de pagamento (MB Way, referência, …)',
        'MainCurrency' => 'a moeda do documento',
    ];

    /** @var array<string, string>|null */
    private ?array $cache = null;

    public function __construct(
        private SettingsRepositoryInterface $repository,
        private ?SettingsLabels $labels = null
    ) {
    }

    /**
     * Every setting, as `setting => value`.
     *
     * @return array<string, string>
     */
    public function all(): array
    {
        if ($this->cache === null) {
            $this->cache = $this->repository->all();
        }

        return $this->cache;
    }

    /**
     * The value of a setting.
     */
    public function get(string $setting, ?string $default = null): ?string
    {
        $value = $this->all()[$setting] ?? '';

        return $value === '' ? $default : $value;
    }

    /**
     * A setting, as a boolean.
     *
     * The WHMCS writes `on` and `off`.
     */
    public function isOn(string $setting, bool $default = false): bool
    {
        $value = strtolower(trim((string)($this->all()[$setting] ?? '')));

        if ($value === '') {
            return $default;
        }

        return in_array($value, ['on', '1', 'yes', 'true'], true);
    }

    /**
     * Write a setting.
     */
    public function set(string $setting, string $value): bool
    {
        $written = $this->repository->set($setting, $value);
        $this->cache = null;

        return $written;
    }

    /**
     * Write several settings at once.
     *
     * @param array<string, string> $values
     */
    public function setMany(array $values): bool
    {
        $written = $this->repository->setMany($values);
        $this->cache = null;

        return $written;
    }

    /**
     * The company, as the documents need it.
     *
     * The WHMCS keeps the company in two places. The `RegistrarAdmin*`
     * ones are the ones that are filled in here, so the resolution falls
     * back to them.
     *
     * @return array<string, string>
     */
    public function company(): array
    {
        $settings = $this->all();

        $resolve = function (string $company, string $registrar) use ($settings): string {
            $value = trim((string)($settings[$company] ?? ''));

            if ($value !== '') {
                return $value;
            }

            return trim((string)($settings[$registrar] ?? ''));
        };

        return [
            'name' => $resolve('CompanyName', 'RegistrarAdminCompanyName'),
            'address' => $resolve('CompanyAddress1', 'RegistrarAdminAddress1'),
            'address2' => $resolve('CompanyAddress2', 'RegistrarAdminAddress2'),
            'city' => $resolve('CompanyCity', 'RegistrarAdminCity'),
            'state' => $resolve('CompanyState', 'RegistrarAdminStateProvince'),
            'postcode' => $resolve('CompanyPostcode', 'RegistrarAdminPostalCode'),
            'country' => $resolve('CompanyCountry', 'RegistrarAdminCountry'),
            'email' => $resolve('CompanyEmail', 'RegistrarAdminEmailAddress'),
            'phone' => $resolve('CompanyPhone', 'RegistrarAdminPhone'),
            'nif' => $resolve('CompanyTaxNumber', 'RegistrarAdminTaxNumber'),
            'vat' => $resolve('CompanyVatNumber', 'RegistrarAdminVatNumber'),
            'paymentInfo' => $resolve('CompanyPaymentInfo', 'RegistrarAdminPaymentInfo'),
            'bankDetails' => $resolve('CompanyBankDetails', 'RegistrarAdminBankDetails'),
            'terms' => $resolve('CompanyTermsOfService', 'RegistrarAdminTermsOfService'),
        ];
    }

    /**
     * The currency of the documents.
     */
    public function currency(): string
    {
        return $this->get('MainCurrency', 'EUR') ?? 'EUR';
    }

    /**
     * The settings that are missing, and what each one blocks.
     *
     * @return list<array{key: string, label: string, why: string}>
     */
    public function missing(): array
    {
        $company = $this->company();
        $missing = [];

        foreach (self::REQUIRED as $key => $why) {
            $value = match ($key) {
                'CompanyAddress1' => $company['address'],
                'CompanyCity' => $company['city'],
                'CompanyPostcode' => $company['postcode'],
                'CompanyCountry' => $company['country'],
                'CompanyEmail' => $company['email'],
                'CompanyTaxNumber' => $company['nif'],
                'CompanyPaymentInfo' => $company['paymentInfo'],
                'CompanyBankDetails' => $company['bankDetails'],
                default => (string)($this->all()[$key] ?? ''),
            };

            if (trim($value) === '') {
                $missing[] = [
                    'key' => $key,
                    'label' => $key,
                    'why' => $why,
                ];
            }
        }

        return $missing;
    }

    /**
     * The settings, grouped by the WHMCS's **sections**.
     *
     * The WHMCS splits the configuration into pages
     * (`admin/config<name>.php`), and so does the panel. The name of
     * each setting is the **real** one — the label of the WHMCS's own
     * language file, or the setting's name humanised the way the
     * WHMCS writes it.
     *
     * @return array<string, array{
     *     label: string,
     *     settings: list<array{key: string, label: string, value: string, editable: bool}>
     * }>
     */
    public function grouped(): array
    {
        $settings = $this->all();
        $labels = $this->labels();

        $groups = [];

        foreach ($settings as $key => $value) {
            $section = $labels->normalise($labels->sectionOf((string)$key));

            $groups[$section]['label'] = $labels->sectionName($section);
            $groups[$section]['settings'][] = [
                'key' => (string)$key,
                'label' => $labels->label((string)$key),
                'value' => (string)$value,
                'editable' => in_array((string)$key, self::EDITABLE, true),
            ];
        }

        // As seccoes sem definicoes nao aparecem; as do WHMCS que as
        // tem aparecem todas.
        foreach ($labels->sections() as $section => $name) {
            if (!isset($groups[$section])) {
                $groups[$section] = ['label' => $name, 'settings' => []];
            }
        }

        foreach ($groups as $key => $group) {
            usort(
                $group['settings'],
                static fn (array $a, array $b): int => strcmp($a['key'], $b['key'])
            );
        }

        // As seccoes com definicoes primeiro, pela ordem do WHMCS.
        $order = array_keys($labels->sections());
        uksort(
            $groups,
            static function (string $a, string $b) use ($order): int {
                $positionA = array_search($a, $order, true);
                $positionB = array_search($b, $order, true);

                $positionA = $positionA === false ? PHP_INT_MAX : $positionA;
                $positionB = $positionB === false ? PHP_INT_MAX : $positionB;

                return $positionA <=> $positionB;
            }
        );

        return $groups;
    }

    /**
     * The labels and the sections.
     */
    private function labels(): SettingsLabels
    {
        if ($this->labels === null) {
            $this->labels = new SettingsLabels();
        }

        return $this->labels;
    }
}
