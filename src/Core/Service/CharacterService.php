<?php

declare(strict_types=1);

namespace UncannyWoW\Core\Service;

use UncannyWoW\Core\Config\ClientConfiguration;
use UncannyWoW\Core\Contract\Repository\CharacterRepositoryInterface;
use UncannyWoW\Core\Domain\Enum\Region;
use UncannyWoW\Core\Domain\Model\Character\CharacterProfile;

class CharacterService
{
    public function __construct(
        private readonly CharacterRepositoryInterface $characterRepository,
        private readonly ClientConfiguration $config,
    ) {}

    /**
     * Retrieve a character profile by canonical Blizzard realm slug and character name.
     *
     * @param string $realm Canonical Blizzard realm slug (e.g. 'la-croisade-ecarlate'). Whitespace is not permitted.
     * @param string $name Character name (e.g. 'norigosa' or Unicode 'nörigosa').
     * @param Region|null $region Optional override for the target region (defaults to client configured region).
     */
    public function profile(string $realm, string $name, ?Region $region = null): CharacterProfile
    {
        $targetRegion = $region ?? $this->config->region;
        return $this->characterRepository->findProfile($targetRegion, $realm, $name);
    }
}
