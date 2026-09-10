<?php

declare(strict_types=1);

namespace UncannyWoW\Core\Contract\Repository;

use UncannyWoW\Core\Domain\Enum\Locale;
use UncannyWoW\Core\Domain\Enum\Region;
use UncannyWoW\Core\Domain\Model\Profession\Profession;
use UncannyWoW\Core\Domain\Model\Profession\SkillTier;

interface ProfessionRepositoryInterface
{
    /**
     * Retrieve a profession by its positive integer ID.
     */
    public function getById(Region $region, int $id, Locale $locale): Profession;

    /**
     * Retrieve an expansion skill tier by profession ID and skill tier ID.
     */
    public function getSkillTier(Region $region, int $professionId, int $skillTierId, Locale $locale): SkillTier;
}
