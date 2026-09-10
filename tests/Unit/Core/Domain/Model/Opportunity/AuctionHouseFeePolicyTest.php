<?php

declare(strict_types=1);

namespace UncannyWoW\Tests\Unit\Core\Domain\Model\Opportunity;

use PHPUnit\Framework\TestCase;
use UncannyWoW\Core\Domain\Model\Opportunity\AuctionHouseFeePolicy;

final class AuctionHouseFeePolicyTest extends TestCase
{
    public function testDefaultPolicyIs500BasisPoints(): void
    {
        $policy = new AuctionHouseFeePolicy();
        self::assertSame(500, $policy->cutBasisPoints);
    }

    public function testCalculateFeeExact5Percent(): void
    {
        $policy = new AuctionHouseFeePolicy();

        // 100 copper -> 5 copper
        self::assertSame(5, $policy->calculateFee(100));

        // 10,000 copper (1 gold) -> 500 copper
        self::assertSame(500, $policy->calculateFee(10000));

        // 0 copper -> 0 fee
        self::assertSame(0, $policy->calculateFee(0));
    }

    public function testCalculateFeeUsesConservativeCeilingRounding(): void
    {
        $policy = new AuctionHouseFeePolicy();

        // 101 copper: 5% is 5.05 copper -> ceiling 6 copper
        self::assertSame(6, $policy->calculateFee(101));

        // 102 copper: 5% is 5.10 copper -> ceiling 6 copper
        self::assertSame(6, $policy->calculateFee(102));

        // 19 copper: 5% is 0.95 copper -> ceiling 1 copper
        self::assertSame(1, $policy->calculateFee(19));

        // 1 copper: 5% is 0.05 copper -> ceiling 1 copper
        self::assertSame(1, $policy->calculateFee(1));
    }

    public function testCustomBasisPointsPolicy(): void
    {
        $zeroCutPolicy = new AuctionHouseFeePolicy(0);
        self::assertSame(0, $zeroCutPolicy->calculateFee(1000));

        $tenPercentPolicy = new AuctionHouseFeePolicy(1000);
        self::assertSame(100, $tenPercentPolicy->calculateFee(1000));
        self::assertSame(11, $tenPercentPolicy->calculateFee(101)); // 10.1 -> 11

        $hundredPercentPolicy = new AuctionHouseFeePolicy(10000);
        self::assertSame(1000, $hundredPercentPolicy->calculateFee(1000));
    }

    public function testNegativeBasisPointsThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new AuctionHouseFeePolicy(-1);
    }

    public function testBasisPointsGreaterThan10000Throws(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new AuctionHouseFeePolicy(10001);
    }

    public function testNegativeGrossAmountThrows(): void
    {
        $policy = new AuctionHouseFeePolicy();
        $this->expectException(\InvalidArgumentException::class);
        $policy->calculateFee(-1);
    }
}
