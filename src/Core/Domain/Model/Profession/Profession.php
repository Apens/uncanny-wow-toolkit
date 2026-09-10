<?php

declare(strict_types=1);

namespace UncannyWoW\Core\Domain\Model\Profession;

final readonly class Profession
{
    /**
     * @param int $id Blizzard profession ID.
     * @param string $name Localized profession name.
     * @param string|null $description Optional localized description.
     * @param list<ProfessionSkillTierSummary> $skillTiers Available expansion skill tiers.
     */
    public function __construct(
        public int $id,
        public string $name,
        public ?string $description = null,
        public array $skillTiers = [],
    ) {
        if ($this->id <= 0) {
            throw new \InvalidArgumentException(sprintf('Profession ID must be positive, got %d.', $this->id));
        }

        if (trim($this->name) === '') {
            throw new \InvalidArgumentException('Profession name must not be empty.');
        }
    }
}
