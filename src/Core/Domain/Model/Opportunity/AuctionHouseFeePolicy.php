<?php

declare(strict_types=1);

namespace UncannyWoW\Core\Domain\Model\Opportunity;

/**
 * Policy governing the Auction House sales cut calculation.
 *
 * The current standard World of Warcraft retail Auction House cut is 5.00% (500 basis points).
 *
 * Rounding Policy Note:
 * Sub-copper fee fractions are rounded up (CEILING). This is a deliberate, conservative
 * toolkit policy to ensure prospective net profit is never overstated. It is not asserted to
 * be the proprietary internal rounding implementation of Blizzard servers.
 *
 * Deposits and relisting losses are excluded from this policy as successful sales refund deposits.
 */
final readonly class AuctionHouseFeePolicy
{
    public const int DEFAULT_CUT_BASIS_POINTS = 500; // 5.00%

    public function __construct(
        public int $cutBasisPoints = self::DEFAULT_CUT_BASIS_POINTS,
    ) {
        if ($this->cutBasisPoints < 0 || $this->cutBasisPoints > 10000) {
            throw new \InvalidArgumentException(sprintf(
                'Cut basis points must be between 0 and 10000 (0%% to 100%%), got %d.',
                $this->cutBasisPoints,
            ));
        }
    }

    /**
     * Calculate the Auction House commission in copper using conservative ceiling rounding.
     *
     * @param int $grossAmountCopper Non-negative gross sale amount in copper.
     */
    public function calculateFee(int $grossAmountCopper): int
    {
        return SafeIntegerMath::mulDivCeil($grossAmountCopper, $this->cutBasisPoints, 10000);
    }
}
