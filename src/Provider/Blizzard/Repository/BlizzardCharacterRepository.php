<?php

declare(strict_types=1);

namespace UncannyWoW\Provider\Blizzard\Repository;

use UncannyWoW\Core\Contract\Repository\CharacterRepositoryInterface;
use UncannyWoW\Core\Domain\Enum\Region;
use UncannyWoW\Core\Domain\Model\Character\CharacterProfile;
use UncannyWoW\Provider\Blizzard\Client\BlizzardApiClient;
use UncannyWoW\Provider\Blizzard\Hydrator\CharacterProfileHydrator;

class BlizzardCharacterRepository implements CharacterRepositoryInterface
{
    public function __construct(
        private readonly BlizzardApiClient $apiClient,
        private readonly CharacterProfileHydrator $hydrator,
    ) {}

    public function findProfile(Region $region, string $realmSlug, string $name): CharacterProfile
    {
        $normalizedRealm = mb_strtolower(trim($realmSlug), 'UTF-8');
        $normalizedName = mb_strtolower(trim($name), 'UTF-8');

        if ($normalizedRealm === '') {
            throw new \InvalidArgumentException('Realm slug cannot be empty.');
        }
        if (preg_match('/\s/', $normalizedRealm) === 1) {
            throw new \InvalidArgumentException('Realm slug cannot contain whitespace. A canonical Blizzard realm slug is required (e.g., "la-croisade-ecarlate").');
        }
        if ($normalizedName === '') {
            throw new \InvalidArgumentException('Character name cannot be empty.');
        }

        $path = sprintf(
            '/profile/wow/character/%s/%s',
            rawurlencode($normalizedRealm),
            rawurlencode($normalizedName),
        );

        $data = $this->apiClient->get(
            region: $region,
            path: $path,
            resourceTypeForNotFound: 'character',
            identifierForNotFound: sprintf('%s:%s:%s', $region->value, $normalizedRealm, $normalizedName),
        );

        return $this->hydrator->hydrate($region, $data);
    }
}
