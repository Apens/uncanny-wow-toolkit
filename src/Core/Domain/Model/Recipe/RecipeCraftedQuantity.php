<?php

declare(strict_types=1);

namespace UncannyWoW\Core\Domain\Model\Recipe;

final readonly class RecipeCraftedQuantity
{
    /**
     * @param int|null $minimum Minimum quantity, or null if unspecified by provider.
     * @param int|null $maximum Maximum quantity, or null if unspecified by provider.
     */
    public function __construct(
        public ?int $minimum = null,
        public ?int $maximum = null,
    ) {
        if ($this->minimum !== null && $this->minimum <= 0) {
            throw new \InvalidArgumentException(sprintf('Minimum crafted quantity must be positive, got %d.', $this->minimum));
        }

        if ($this->maximum !== null && $this->maximum <= 0) {
            throw new \InvalidArgumentException(sprintf('Maximum crafted quantity must be positive, got %d.', $this->maximum));
        }

        if ($this->minimum !== null && $this->maximum !== null && $this->maximum < $this->minimum) {
            throw new \InvalidArgumentException(sprintf(
                'Maximum crafted quantity (%d) cannot be less than minimum (%d).',
                $this->maximum,
                $this->minimum,
            ));
        }
    }

    public static function fixed(int $quantity): self
    {
        return new self($quantity, $quantity);
    }

    public static function range(int $minimum, int $maximum): self
    {
        return new self($minimum, $maximum);
    }

    public static function unknown(): self
    {
        return new self(null, null);
    }

    public function isKnown(): bool
    {
        return $this->minimum !== null && $this->maximum !== null;
    }

    public function isFixed(): bool
    {
        return $this->isKnown() && $this->minimum === $this->maximum;
    }

    public function isRange(): bool
    {
        return $this->isKnown() && $this->minimum < $this->maximum;
    }
}
