<?php

declare(strict_types=1);

namespace UncannyWoW\Core\Domain\Model\Crafting;

final readonly class OutputPriceResolution
{
    public function __construct(
        public CraftOutputTarget $outputTarget,
        public OutputSalePriceAssumption $assumption,
        public OutputPriceStatus $status,
        public ?int $unitSalePriceCopper = null,
    ) {
        if ($this->isPriced()) {
            if ($this->unitSalePriceCopper === null) {
                throw new \InvalidArgumentException('Priced OutputPriceResolution must provide a non-null unitSalePriceCopper.');
            }
            if ($this->unitSalePriceCopper < 0) {
                throw new \InvalidArgumentException(sprintf('Unit sale price cannot be negative, got %d copper.', $this->unitSalePriceCopper));
            }
        } else {
            if ($this->unitSalePriceCopper !== null) {
                throw new \InvalidArgumentException('Unpriced OutputPriceResolution cannot contain a unitSalePriceCopper.');
            }
        }
    }

    public static function pricedFromCurrentAsk(CraftOutputTarget $target, CurrentLowestAsk $assumption, int $unitSalePriceCopper): self
    {
        return new self($target, $assumption, OutputPriceStatus::PricedFromCurrentAsk, $unitSalePriceCopper);
    }

    public static function customPriced(CraftOutputTarget $target, CustomUnitSalePrice $assumption): self
    {
        return new self($target, $assumption, OutputPriceStatus::CustomPriced, $assumption->unitPriceCopper);
    }

    public static function marketDataRequired(CraftOutputTarget $target, OutputSalePriceAssumption $assumption): self
    {
        return new self($target, $assumption, OutputPriceStatus::MarketDataRequired, null);
    }

    public static function marketUnavailable(CraftOutputTarget $target, OutputSalePriceAssumption $assumption): self
    {
        return new self($target, $assumption, OutputPriceStatus::MarketUnavailable, null);
    }

    public static function ambiguousNonCommodityLot(CraftOutputTarget $target, OutputSalePriceAssumption $assumption): self
    {
        return new self($target, $assumption, OutputPriceStatus::AmbiguousNonCommodityLot, null);
    }

    public function isPriced(): bool
    {
        return $this->status === OutputPriceStatus::PricedFromCurrentAsk
            || $this->status === OutputPriceStatus::CustomPriced;
    }
}
