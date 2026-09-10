<?php

declare(strict_types=1);

namespace UncannyWoW\Core\Domain\Model\Crafting;

use UncannyWoW\Core\Domain\Math\ExactFraction;

final readonly class CrafterState
{
    /**
     * @param array<string, ExactFraction> $resourcefulnessSavings Map of reagent key => expected saved quantity
     */
    public function __construct(
        public array $resourcefulnessSavings = [],
        public ?ExactFraction $multicraftExtraOutput = null,
        public ?ExactFraction $ingenuityConcentrationRefund = null,
        public ?string $sourceLabel = null,
    ) {
        if ($this->multicraftExtraOutput !== null && $this->multicraftExtraOutput->isNegative()) {
            throw new \InvalidArgumentException(sprintf('Multicraft extra output expectation cannot be negative, got %s.', $this->multicraftExtraOutput));
        }

        if ($this->ingenuityConcentrationRefund !== null && $this->ingenuityConcentrationRefund->isNegative()) {
            throw new \InvalidArgumentException(sprintf('Ingenuity concentration refund expectation cannot be negative, got %s.', $this->ingenuityConcentrationRefund));
        }

        foreach ($this->resourcefulnessSavings as $key => $savedQuantity) {
            if ($savedQuantity->isNegative()) {
                throw new \InvalidArgumentException(sprintf('Resourcefulness expected saved quantity for reagent "%s" cannot be negative, got %s.', $key, $savedQuantity));
            }
        }
    }

    public static function none(): self
    {
        return new self();
    }
}
