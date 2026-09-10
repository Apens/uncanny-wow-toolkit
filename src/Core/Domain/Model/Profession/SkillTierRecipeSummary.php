<?php

declare(strict_types=1);

namespace UncannyWoW\Core\Domain\Model\Profession;

final readonly class SkillTierRecipeSummary
{
    public function __construct(
        public int $id,
        public string $name,
    ) {
        if ($this->id <= 0) {
            throw new \InvalidArgumentException(sprintf('Recipe ID must be positive, got %d.', $this->id));
        }

        if (trim($this->name) === '') {
            throw new \InvalidArgumentException('Recipe name must not be empty.');
        }
    }
}
