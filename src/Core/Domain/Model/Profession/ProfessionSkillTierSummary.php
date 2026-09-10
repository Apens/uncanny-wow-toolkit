<?php

declare(strict_types=1);

namespace UncannyWoW\Core\Domain\Model\Profession;

final readonly class ProfessionSkillTierSummary
{
    public function __construct(
        public int $id,
        public string $name,
    ) {
        if ($this->id <= 0) {
            throw new \InvalidArgumentException(sprintf('Skill tier ID must be positive, got %d.', $this->id));
        }

        if (trim($this->name) === '') {
            throw new \InvalidArgumentException('Skill tier name must not be empty.');
        }
    }
}
