<?php

declare(strict_types=1);

namespace Xkhm\Service;

/**
 * The price and the renewal term of a service.
 *
 * A service has a **price** and a **billing cycle**, and changing one
 * of them half way through the period owes the customer a
 * **proration**: the difference, proportional to the time that is left.
 *
 * ## The quote, and then the change
 *
 * The calculation depends on the clock, so it is **quoted first** and
 * applied afterwards — and the applied value is the quoted one. A
 * proration that applies itself takes money (or gives it) without
 * anybody having seen the arithmetic.
 *
 * ## Where the price comes from
 *
 * The product carries a price **per cycle** (`tblpricing`). Changing
 * the cycle therefore brings the product's price for that cycle.
 * The price itself can also be set by hand — the WHMCS allows it, and
 * this installation has discounts that the product's price does not
 * know about.
 */
class ServiceTermsService
{
    /**
     * The length of each cycle, in days.
     *
     * The WHMCS's own lengths, rounded.
     */
    public const CYCLE_DAYS = [
        'One Time' => 0,
        'Monthly' => 30,
        'Quarterly' => 90,
        'Semi Annually' => 180,
        'Annually' => 365,
        'Biennially' => 730,
        'Triennially' => 1095,
    ];

    /**
     * The cycles the pricing table knows, by their column.
     *
     * @var array<string, string>
     */
    public const PRICING_COLUMNS = [
        'monthly' => 'Monthly',
        'quarterly' => 'Quarterly',
        'semiannually' => 'Semi Annually',
        'annually' => 'Annually',
        'biennially' => 'Biennially',
        'triennially' => 'Triennially',
    ];

    public function __construct(
        private \Xkhm\Repository\AdminPanelRepositoryInterface $repository,
        private CreditService $credit
    ) {
    }

    /**
     * The cycles the product offers, with their price.
     *
     * @return array<string, float>
     */
    public function availableCycles(int $packageId): array
    {
        $cycles = [];

        foreach ($this->repository->productPrices($packageId) as $column => $price) {
            // `-1.00` is how the WHMCS says "this product does not
            // have this cycle".
            if ($price < 0) {
                continue;
            }

            $cycle = self::PRICING_COLUMNS[$column] ?? null;

            if ($cycle !== null) {
                $cycles[$cycle] = $price;
            }
        }

        return $cycles;
    }

    /**
     * What the change would be.
     *
     * @return array{
     *     service: array<string, mixed>,
     *     currentPrice: float,
     *     currentCycle: string,
     *     newPrice: float,
     *     newCycle: string,
     *     nextDueDate: string,
     *     daysRemaining: int,
     *     fraction: float,
     *     difference: float,
     *     credit: float,
     *     note: string
     * }
     */
    public function quote(
        int $serviceId,
        string $billingCycle,
        ?float $amount = null
    ): ?array {
        $service = $this->repository->findService($serviceId);

        if ($service === null) {
            return null;
        }

        $cycle = trim($billingCycle);

        if (!isset(self::CYCLE_DAYS[$cycle])) {
            return null;
        }

        $currentPrice = (float)($service['amount'] ?? 0);
        $currentCycle = (string)($service['billingcycle'] ?? '');

        // The price of the new cycle, unless one was given by hand.
        $newPrice = $amount;

        if ($newPrice === null) {
            $cycles = $this->availableCycles((int)($service['packageid'] ?? 0));
            $newPrice = $cycles[$cycle] ?? $currentPrice;
        }

        $newPrice = round($newPrice, 2);

        // The new renewal date, from the new cycle.
        $now = time();
        $cycleDays = self::CYCLE_DAYS[$cycle];
        $nextDue = $cycleDays > 0
            ? date('Y-m-d', strtotime('+' . $cycleDays . ' days', $now))
            : (string)($service['nextduedate'] ?? date('Y-m-d', $now));

        // The proration: the difference, for the part of the cycle that
        // is left.
        $daysRemaining = 0;
        $fraction = 0.0;
        $difference = 0.0;

        $currentDue = strtotime((string)($service['nextduedate'] ?? ''));

        if ($currentDue !== false) {
            $daysRemaining = (int)floor(($currentDue - $now) / 86400);

            $currentCycleDays = self::CYCLE_DAYS[$currentCycle] ?? 365;

            if ($currentCycleDays > 0) {
                $fraction = max(0.0, min(1.0, $daysRemaining / $currentCycleDays));
            }

            $difference = round(($newPrice - $currentPrice) * $fraction, 2);
        }

        return [
            'service' => $service,
            'currentPrice' => $currentPrice,
            'currentCycle' => $currentCycle,
            'newPrice' => $newPrice,
            'newCycle' => $cycle,
            'nextDueDate' => $nextDue,
            'daysRemaining' => $daysRemaining,
            'fraction' => $fraction,
            'difference' => $difference,
            // A negative difference is in the customer's favour.
            'credit' => $difference < 0 ? round(-$difference, 2) : 0.0,
            'note' => $this->note($service, $currentPrice, $currentCycle, $newPrice, $cycle, $difference),
        ];
    }

    /**
     * Apply the change.
     *
     * @return array<string, mixed>|null the quote that was applied
     */
    public function apply(
        int $serviceId,
        string $billingCycle,
        ?float $amount = null,
        int $adminId = 0
    ): ?array {
        $quote = $this->quote($serviceId, $billingCycle, $amount);

        if ($quote === null) {
            return null;
        }

        $service = $quote['service'];
        $clientId = (int)($service['userid'] ?? 0);

        if (!$this->repository->updateServiceTerms(
            $serviceId,
            (float)$quote['newPrice'],
            (string)$quote['newCycle'],
            (string)$quote['nextDueDate']
        )) {
            return null;
        }

        // The difference in the customer's favour becomes credit.
        if ((float)$quote['credit'] > 0.0 && $clientId > 0) {
            $this->credit->addCredit(
                $clientId,
                (float)$quote['credit'],
                sprintf(
                    'Prorrateio: alteração do preço do serviço #%d',
                    $serviceId
                )
            );
        }

        // And the change is written in the client's notes — as the
        // WHMCS does.
        $this->repository->addNote(
            $clientId,
            $adminId,
            (string)$quote['note']
        );

        return $quote;
    }

    /**
     * The note of the change.
     *
     * @param array<string, mixed> $service
     */
    private function note(
        array $service,
        float $currentPrice,
        string $currentCycle,
        float $newPrice,
        string $newCycle,
        float $difference
    ): string {
        $lines = [];

        $lines[] = sprintf(
            'Alteração das condições do serviço #%d (%s)',
            (int)($service['id'] ?? 0),
            (string)($service['domain'] ?? '')
        );

        $lines[] = sprintf(
            'Preço: %.2f € (%s) → %.2f € (%s)',
            $currentPrice,
            $currentCycle,
            $newPrice,
            $newCycle
        );

        if ($difference != 0.0) {
            $lines[] = sprintf(
                'Prorrateio: %.2f € (%s)',
                $difference,
                $difference < 0 ? 'crédito do cliente' : 'a facturar na próxima renovação'
            );
        }

        return implode(PHP_EOL, $lines);
    }
}
