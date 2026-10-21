<?php

declare(strict_types=1);

namespace Xkhm\Service;

use Xkhm\Repository\DomainRepositoryInterface;

class DomainService
{
    private DomainRepositoryInterface $domainRepository;

    public function __construct(DomainRepositoryInterface $domainRepository)
    {
        $this->domainRepository = $domainRepository;
    }

    /**
     * Check domain availability across multiple TLDs.
     * Returns array of [domain => available, tld => tld, price => price]
     */
    public function checkAvailability(string $domain, ?array $tlds = null): array
    {
        $results = [];
        $tlds = $tlds ?? ['.com', '.net', '.org', '.com.br', '.net.br', '.org.br'];

        foreach ($tlds as $tld) {
            $fullDomain = $domain . $tld;
            $results[] = [
                'domain' => $fullDomain,
                'tld' => $tld,
                'available' => !$this->isRegistered($fullDomain),
                'price' => $this->getTLDPrice($tld),
            ];
        }

        return $results;
    }

    /**
     * Check if a domain is already registered.
     */
    public function isRegistered(string $domain): bool
    {
        $existing = $this->domainRepository->findByName($domain);
        return $existing !== null;
    }

    /**
     * Get TLD pricing from tbldomainspricing.
     */
    public function getTLDPrice(string $tld): float
    {
        $prices = $this->domainRepository->getTLDPrices();
        return $prices[$tld] ?? 0.0;
    }

    /**
     * Register a new domain.
     * Returns [success, message, domain_id]
     */
    public function registerDomain(string $domain, int $clientId, array $data): array
    {
        if ($this->isRegistered($domain)) {
            return [false, 'Domain already registered', null];
        }

        $tld = $this->extractTLD($domain);
        $period = max(1, (int)($data['period'] ?? 1));

        // The column names follow the WHMCS `tbldomains` schema exactly:
        // `domain` (not `name`), `registrationdate` (not `regdate`),
        // `period` (not `registrationperiod`). There is no `tld`, `price`
        // or `transfer` column — the TLD is part of `domain` and the price
        // lives in `tbldomainpricing`.
        $domainData = [
            'domain' => $domain,
            'userid' => $clientId,
            // `orderid` is NOT NULL without a default in WHMCS 5.3.9.
            'orderid' => 0,
            'period' => $period,
            'registrationdate' => date('Y-m-d'),
            'expirydate' => date('Y-m-d', strtotime("+{$period} years")),
            'nextduedate' => date('Y-m-d', strtotime("+{$period} years")),
            'status' => 'Active',
            'registrar' => 'linuxkafe',
            'dateadded' => date('Y-m-d H:i:s'),
            'dns1' => $data['dns1'] ?? 'ns1.linuxkafe.com',
            'dns2' => $data['dns2'] ?? 'ns2.linuxkafe.com',
            'currency' => 1,
        ];

        $domainId = $this->domainRepository->create($domainData);

        return [true, 'Domain registered successfully', $domainId];
    }

    /**
     * Renew an existing domain.
     */
    public function renewDomain(int $domainId, int $years = 1): array
    {
        $domain = $this->domainRepository->findById($domainId);
        if (!$domain) {
            return [false, 'Domain not found', null];
        }

        $currentExpiry = strtotime($domain['expirydate']);
        $newExpiry = date('Y-m-d', strtotime("+{$years} years", $currentExpiry));

        $this->domainRepository->update($domainId, [
            'expirydate' => $newExpiry,
            'status' => 'Active',
        ]);

        return [true, "Domain renewed for {$years} year(s)", $domainId];
    }

    /**
     * Transfer a domain to this account.
     */
    public function transferDomain(string $domain, int $clientId, array $data): array
    {
        if ($this->isRegistered($domain)) {
            return [false, 'Domain already registered', null];
        }

        $period = max(1, (int)($data['period'] ?? 1));

        // Same WHMCS column contract as registerDomain(). The transfer flag
        // lives in `tbldomains` as `transfer` only in newer WHMCS releases;
        // the 5.3.x schema has no such column, so it is not written.
        $domainData = [
            'domain' => $domain,
            'userid' => $clientId,
            // `orderid` is NOT NULL without a default in WHMCS 5.3.9.
            'orderid' => 0,
            'period' => $period,
            'registrationdate' => date('Y-m-d'),
            'expirydate' => date('Y-m-d', strtotime("+{$period} years")),
            'nextduedate' => date('Y-m-d', strtotime("+{$period} years")),
            'status' => 'Active',
            'registrar' => 'linuxkafe',
            'dateadded' => date('Y-m-d H:i:s'),
            'dns1' => $data['dns1'] ?? 'ns1.linuxkafe.com',
            'dns2' => $data['dns2'] ?? 'ns2.linuxkafe.com',
            'currency' => 1,
        ];

        $domainId = $this->domainRepository->create($domainData);

        return [true, 'Domain transfer initiated', $domainId];
    }

    /**
     * Get all domains for a client.
     */
    public function getClientDomains(int $clientId): array
    {
        return $this->domainRepository->findByUserId($clientId);
    }

    /**
     * Get domain details with DNS records.
     */
    public function getDomainDetails(int $domainId): ?array
    {
        return $this->domainRepository->findByIdWithDns($domainId);
    }

    /**
     * Update domain DNS records.
     */
    public function updateDNS(int $domainId, array $dnsRecords): bool
    {
        return $this->domainRepository->update($domainId, [
            'dns1' => $dnsRecords['dns1'] ?? '',
            'dns2' => $dnsRecords['dns2'] ?? '',
        ]);
    }

    /**
     * Extract TLD from domain name.
     */
    private function extractTLD(string $domain): string
    {
        $parts = explode('.', $domain);
        return '.' . end($parts);
    }

    /**
     * Get popular TLDs for display.
     */
    public function getPopularTLDs(): array
    {
        return ['.com', '.net', '.org', '.com.br', '.net.br', '.info', '.biz'];
    }
}
