<?php

declare(strict_types=1);

namespace UncannyWoW\Provider\Blizzard\Repository;

use UncannyWoW\Core\Contract\Repository\RecipeRepositoryInterface;
use UncannyWoW\Core\Domain\Enum\Locale;
use UncannyWoW\Core\Domain\Enum\Region;
use UncannyWoW\Core\Domain\Model\Recipe\Recipe;
use UncannyWoW\Provider\Blizzard\Client\BlizzardApiClient;
use UncannyWoW\Provider\Blizzard\Client\BlizzardApiEndpointResolver;
use UncannyWoW\Provider\Blizzard\Hydrator\RecipeHydrator;

class BlizzardRecipeRepository implements RecipeRepositoryInterface
{
    public function __construct(
        private readonly BlizzardApiClient $apiClient,
        private readonly RecipeHydrator $hydrator,
    ) {}

    public function getById(Region $region, int $id, Locale $locale): Recipe
    {
        if ($id <= 0) {
            throw new \InvalidArgumentException(sprintf('Recipe ID must be a positive integer, got %d.', $id));
        }

        $path = sprintf('/data/wow/recipe/%d', $id);

        $data = $this->apiClient->get(
            region: $region,
            path: $path,
            locale: $locale,
            namespace: BlizzardApiEndpointResolver::resolveStaticNamespace($region),
            resourceTypeForNotFound: 'recipe',
            identifierForNotFound: sprintf('%s:%d', $region->value, $id),
        );

        return $this->hydrator->hydrate($data);
    }
}
