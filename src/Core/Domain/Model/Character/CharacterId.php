<?php

declare(strict_types=1);

namespace UncannyWoW\Core\Domain\Model\Character;

use UncannyWoW\Core\Domain\Enum\Region;

readonly class CharacterId
{
    public string $realmSlug;
    public string $characterName;

    public function __construct(
        public Region $region,
        string $realmSlug,
        string $characterName,
    ) {
        $normalizedRealm = mb_strtolower(trim($realmSlug), 'UTF-8');
        $normalizedName = mb_strtolower(trim($characterName), 'UTF-8');

        if ($normalizedRealm === '') {
            throw new \InvalidArgumentException('Realm slug cannot be empty.');
        }
        if (preg_match('/\s/', $normalizedRealm) === 1) {
            throw new \InvalidArgumentException('Realm slug cannot contain whitespace. A canonical Blizzard realm slug is required (e.g., "la-croisade-ecarlate").');
        }
        if ($normalizedName === '') {
            throw new \InvalidArgumentException('Character name cannot be empty.');
        }

        $this->realmSlug = $normalizedRealm;
        $this->characterName = $normalizedName;
    }
}
