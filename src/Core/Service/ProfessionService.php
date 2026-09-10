<?php

declare(strict_types=1);

namespace UncannyWoW\Core\Service;

use UncannyWoW\Core\Config\ClientConfiguration;
use UncannyWoW\Core\Contract\Repository\ProfessionRepositoryInterface;
use UncannyWoW\Core\Domain\Enum\Locale;
use UncannyWoW\Core\Domain\Enum\Region;
use UncannyWoW\Core\Domain\Model\Profession\Profession;
use UncannyWoW\Core\Domain\Model\Profession\SkillTier;

class ProfessionService
{
    public function __construct(
        private readonly ProfessionRepositoryInterface $professionRepository,
        private readonly ClientConfiguration $config,
    ) {}

    /**
     * Retrieve a profession by its unique numeric ID.
     *
     * @param int $id Positive integer profession ID.
     * @param Region|null $region Optional override for target region (defaults to client configured region).
     * @param Locale|null $locale Optional override for target locale (defaults to client configured locale).
     */
    public function get(int $id, ?Region $region = null, ?Locale $locale = null): Profession
    {
        if ($id <= 0) {
            throw new \InvalidArgumentException(sprintf('Profession ID must be a positive integer, got %d.', $id));
        }

        $targetRegion = $region ?? $this->config->region;
        $targetLocale = $locale ?? $this->config->defaultLocale;

        return $this->professionRepository->getById($targetRegion, $id, $targetLocale);
    }

    /**
     * Retrieve a profession skill tier by profession ID and skill tier ID.
     *
     * @param int $professionId Positive integer profession ID.
     * @param int $skillTierId Positive integer skill tier ID.
     * @param Region|null $region Optional override for target region (defaults to client configured region).
     * @param Locale|null $locale Optional override for target locale (defaults to client configured locale).
     */
    public function skillTier(int $professionId, int $skillTierId, ?Region $region = null, ?Locale $locale = null): SkillTier
    {
        if ($professionId <= 0) {
            throw new \InvalidArgumentException(sprintf('Profession ID must be a positive integer, got %d.', $professionId));
        }

        if ($skillTierId <= 0) {
            throw new \InvalidArgumentException(sprintf('Skill tier ID must be a positive integer, got %d.', $skillTierId));
        }

        $targetRegion = $region ?? $this->config->region;
        $targetLocale = $locale ?? $this->config->defaultLocale;

        return $this->professionRepository->getSkillTier($targetRegion, $professionId, $skillTierId, $targetLocale);
    }
}
