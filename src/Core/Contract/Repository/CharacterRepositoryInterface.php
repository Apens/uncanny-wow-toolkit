<?php

declare(strict_types=1);

namespace UncannyWoW\Core\Contract\Repository;

use UncannyWoW\Core\Domain\Enum\Region;
use UncannyWoW\Core\Domain\Model\Character\CharacterProfile;

interface CharacterRepositoryInterface
{
    public function findProfile(Region $region, string $realm, string $name): CharacterProfile;
}
