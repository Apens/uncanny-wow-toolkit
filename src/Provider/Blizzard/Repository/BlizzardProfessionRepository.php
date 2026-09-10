<?php

declare(strict_types=1);

namespace UncannyWoW\Provider\Blizzard\Repository;

use UncannyWoW\Core\Contract\Repository\ProfessionRepositoryInterface;
use UncannyWoW\Core\Domain\Enum\Locale;
use UncannyWoW\Core\Domain\Enum\Region;
use UncannyWoW\Core\Domain\Model\Profession\Profession;
use UncannyWoW\Core\Domain\Model\Profession\SkillTier;
use UncannyWoW\Provider\Blizzard\Client\BlizzardApiClient;
use UncannyWoW\Provider\Blizzard\Client\BlizzardApiEndpointResolver;
use UncannyWoW\Provider\Blizzard\Hydrator\ProfessionHydrator;

class BlizzardProfessionRepository implements ProfessionRepositoryInterface
{
    public function __construct(
        private readonly BlizzardApiClient $apiClient,
        private readonly ProfessionHydrator $hydrator,
    ) {}

    public function getById(Region $region, int $id, Locale $locale): Profession
    {
        if ($id <= 0) {
            throw new \InvalidArgumentException(sprintf('Profession ID must be a positive integer, got %d.', $id));
        }

        $path = sprintf('/data/wow/profession/%d', $id);

        $data = $this->apiClient->get(
            region: $region,
            path: $path,
            locale: $locale,
            namespace: BlizzardApiEndpointResolver::resolveStaticNamespace($region),
            resourceTypeForNotFound: 'profession',
            identifierForNotFound: sprintf('%s:%d', $region->value, $id),
        );

        return $this->hydrator->hydrateProfession($data);
    }

    public function getSkillTier(Region $region, int $professionId, int $skillTierId, Locale $locale): SkillTier
    {
        if ($professionId <= 0) {
            throw new \InvalidArgumentException(sprintf('Profession ID must be a positive integer, got %d.', $professionId));
        }

        if ($skillTierId <= 0) {
            throw new \InvalidArgumentException(sprintf('Skill tier ID must be a positive integer, got %d.', $skillTierId));
        }

        $path = sprintf('/data/wow/profession/%d/skill-tier/%d', $professionId, $skillTierId);

        $data = $this->apiClient->get(
            region: $region,
            path: $path,
            locale: $locale,
            namespace: BlizzardApiEndpointResolver::resolveStaticNamespace($region),
            resourceTypeForNotFound: 'skill_tier',
            identifierForNotFound: sprintf('%s:%d:%d', $region->value, $professionId, $skillTierId),
        );

        return $this->hydrator->hydrateSkillTier($data, $professionId);
    }
}
