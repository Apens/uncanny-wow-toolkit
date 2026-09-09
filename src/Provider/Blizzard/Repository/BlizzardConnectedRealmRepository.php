<?php

declare(strict_types=1);

namespace UncannyWoW\Provider\Blizzard\Repository;

use UncannyWoW\Core\Contract\Repository\ConnectedRealmRepositoryInterface;
use UncannyWoW\Core\Domain\Enum\Locale;
use UncannyWoW\Core\Domain\Enum\Region;
use UncannyWoW\Core\Domain\Model\ConnectedRealm\ConnectedRealm;
use UncannyWoW\Provider\Blizzard\Client\BlizzardApiClient;
use UncannyWoW\Provider\Blizzard\Client\BlizzardApiEndpointResolver;
use UncannyWoW\Provider\Blizzard\Hydrator\ConnectedRealmHydrator;

class BlizzardConnectedRealmRepository implements ConnectedRealmRepositoryInterface
{
    public function __construct(
        private readonly BlizzardApiClient $apiClient,
        private readonly ConnectedRealmHydrator $hydrator,
    ) {}

    public function getById(Region $region, int $id, Locale $locale): ConnectedRealm
    {
        if ($id <= 0) {
            throw new \InvalidArgumentException(sprintf('Connected realm ID must be a positive integer, got %d.', $id));
        }

        $path = sprintf('/data/wow/connected-realm/%d', $id);

        $data = $this->apiClient->get(
            region: $region,
            path: $path,
            locale: $locale,
            namespace: BlizzardApiEndpointResolver::resolveDynamicNamespace($region),
            resourceTypeForNotFound: 'connected-realm',
            identifierForNotFound: sprintf('%s:%d', $region->value, $id),
        );

        return $this->hydrator->hydrate($data);
    }
}
