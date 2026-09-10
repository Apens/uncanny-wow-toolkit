<?php

declare(strict_types=1);

namespace UncannyWoW\Provider\Blizzard\Repository;

use Psr\Cache\CacheItemPoolInterface;
use UncannyWoW\Core\Contract\Repository\RecipeRepositoryInterface;
use UncannyWoW\Core\Domain\Enum\Locale;
use UncannyWoW\Core\Domain\Enum\Region;
use UncannyWoW\Core\Domain\Model\Recipe\Recipe;

class CachedRecipeRepository implements RecipeRepositoryInterface
{
    public function __construct(
        private readonly RecipeRepositoryInterface $innerRepository,
        private readonly ?CacheItemPoolInterface $cachePool = null,
        private readonly int $defaultTtlSeconds = 86400,
    ) {}

    public function getById(Region $region, int $id, Locale $locale): Recipe
    {
        if ($this->cachePool === null) {
            return $this->innerRepository->getById($region, $id, $locale);
        }

        $cacheKey = $this->buildCacheKey($region, $id, $locale);
        $cacheItem = $this->cachePool->getItem($cacheKey);

        if ($cacheItem->isHit()) {
            /** @var Recipe $cachedItem */
            $cachedItem = $cacheItem->get();
            return $cachedItem;
        }

        $recipe = $this->innerRepository->getById($region, $id, $locale);

        if ($this->defaultTtlSeconds > 0) {
            $cacheItem->set($recipe);
            $cacheItem->expiresAfter($this->defaultTtlSeconds);
            $this->cachePool->save($cacheItem);
        }

        return $recipe;
    }

    private function buildCacheKey(Region $region, int $id, Locale $locale): string
    {
        $localeKey = strtolower($locale->value);

        $identity = sprintf('%s:%s:%d', strtolower($region->value), $localeKey, $id);
        $hash = substr(hash('sha256', $identity), 0, 48);

        return sprintf(
            'uw.recipe.%s.%s',
            strtolower($region->value),
            $hash,
        );
    }
}
