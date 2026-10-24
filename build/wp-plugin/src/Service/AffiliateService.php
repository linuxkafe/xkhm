<?php

declare(strict_types=1);

namespace Xkhm\Service;

use Xkhm\Repository\AffiliateRepositoryInterface;

class AffiliateService
{
    private AffiliateRepositoryInterface $repository;

    public function __construct(AffiliateRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    /**
     * Register a new affiliate account.
     */
    public function register(int $clientId, string $name, string $email, float $commissionRate = 10.0): ?int
    {
        // Check if already an affiliate
        $existing = $this->repository->findByUserId($clientId);
        if ($existing) {
            return null;
        }

        return $this->repository->create([
            'userid' => $clientId,
            'name' => $name,
            'email' => $email,
            'commission' => $commissionRate,
            'datecreated' => date('Y-m-d'),
        ]);
    }

    /**
     * Get affiliate details.
     */
    public function getAffiliate(int $affiliateId): ?array
    {
        return $this->repository->findById($affiliateId);
    }

    /**
     * Get affiliate by ref code.
     */
    public function getAffiliateByRefCode(string $code): ?array
    {
        return $this->repository->findByRefCode($code);
    }

    /**
     * Get the affiliate record owned by a client.
     */
    public function getAffiliateByUserId(int $userId): ?array
    {
        return $this->repository->findByUserId($userId);
    }

    /**
     * Get referral count for affiliate.
     */
    public function getReferralCount(int $affiliateId): int
    {
        $referrals = $this->repository->getReferralsByAffiliate($affiliateId);
        return count($referrals);
    }

    /**
     * Calculate total earnings.
     */
    public function getEarnings(int $affiliateId): float
    {
        return $this->repository->getEarningsByAffiliate($affiliateId);
    }

    /**
     * Get referrals list.
     */
    public function getReferrals(int $affiliateId): array
    {
        return $this->repository->getReferralsByAffiliate($affiliateId);
    }

    /**
     * Track a referral from a referral link click.
     * Returns [affiliate_id, success]
     */
    public function trackReferral(string $refCode, int $clientId): array
    {
        $affiliate = $this->repository->findByRefCode($refCode);
        if (!$affiliate) {
            return [null, false];
        }

        // Check if already tracked
        $existing = $this->repository->getReferralsByAffiliate($affiliate['id']);
        foreach ($existing as $ref) {
            if ($ref['clientid'] === $clientId) {
                return [$affiliate['id'], true];
            }
        }

        $this->repository->addReferral([
            'affiliateid' => $affiliate['id'],
            'clientid' => $clientId,
            'date' => date('Y-m-d'),
            'commission' => 0, // Will be updated on purchase
        ]);

        return [$affiliate['id'], true];
    }

    /**
     * Add commission to affiliate for a purchase.
     */
    public function addCommission(int $affiliateId, float $amount, int $invoiceId): bool
    {
        // Update affiliate balance
        $affiliate = $this->repository->findById($affiliateId);
        if (!$affiliate) {
            return false;
        }

        $newBalance = (float)$affiliate['balance'] + $amount;
        $this->repository->findById($affiliateId); // exists check

        // TODO: Update tblaffiliates balance
        // This would require an update method on the repository

        return true;
    }

    /**
     * Get payout history.
     */
    public function getPayoutHistory(int $affiliateId): array
    {
        return $this->repository->getPayoutHistory($affiliateId);
    }

    /**
     * Generate affiliate link.
     */
    public function getAffiliateLink(int $affiliateId): string
    {
        $affiliate = $this->repository->findById($affiliateId);
        if (!$affiliate) {
            return '';
        }
        return '/cart.php?aff=' . urlencode($affiliate['refcode']);
    }
}
