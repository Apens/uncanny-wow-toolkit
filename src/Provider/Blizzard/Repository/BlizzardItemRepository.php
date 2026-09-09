<?php

declare(strict_types=1);

namespace UncannyWoW\Provider\Blizzard\Repository;

use UncannyWoW\Core\Contract\Repository\ItemRepositoryInterface;
use UncannyWoW\Core\Domain\Enum\Locale;
use UncannyWoW\Core\Domain\Enum\Region;
use UncannyWoW\Core\Domain\Model\Item\Item;
use UncannyWoW\Provider\Blizzard\Client\BlizzardApiClient;
use UncannyWoW\Provider\Blizzard\Client\BlizzardApiEndpointResolver;
use UncannyWoW\Provider\Blizzard\Hydrator\ItemHydrator;

class BlizzardItemRepository implements ItemRepositoryInterface
{
    public function __construct(
        private readonly BlizzardApiClient $apiClient,
        private readonly ItemHydrator $hydrator,
    ) {}

    public function getById(Region $region, int $id, Locale $locale): Item
    {
        if ($id <= 0) {
            throw new \InvalidArgumentException(sprintf('Item ID must be a positive integer, got %d.', $id));
        }

        $path = sprintf('/data/wow/item/%d', $id);

        $data = $this->apiClient->get(
            region: $region,
            path: $path,
            locale: $locale,
            namespace: BlizzardApiEndpointResolver::resolveStaticNamespace($region),
            resourceTypeForNotFound: 'item',
            identifierForNotFound: sprintf('%s:%d', $region->value, $id),
        );

        return $this->hydrator->hydrate($data);
    }
}
