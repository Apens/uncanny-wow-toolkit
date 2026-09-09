<?php

declare(strict_types=1);

namespace UncannyWoW\Provider\Blizzard\Repository;

use Psr\Cache\CacheItemPoolInterface;
use UncannyWoW\Core\Contract\Repository\ItemRepositoryInterface;
use UncannyWoW\Core\Domain\Enum\Locale;
use UncannyWoW\Core\Domain\Enum\Region;
use UncannyWoW\Core\Domain\Model\Item\Item;

class CachedItemRepository implements ItemRepositoryInterface
{
    public function __construct(
        private readonly ItemRepositoryInterface $innerRepository,
        private readonly ?CacheItemPoolInterface $cachePool = null,
        private readonly int $defaultTtlSeconds = 86400,
    ) {}

    public function getById(Region $region, int $id, Locale $locale): Item
    {
        if ($this->cachePool === null) {
            return $this->innerRepository->getById($region, $id, $locale);
        }

        $cacheKey = $this->buildCacheKey($region, $id, $locale);
        $cacheItem = $this->cachePool->getItem($cacheKey);

        if ($cacheItem->isHit()) {
            /** @var Item $cachedItem */
            $cachedItem = $cacheItem->get();
            return $cachedItem;
        }

        $item = $this->innerRepository->getById($region, $id, $locale);

        if ($this->defaultTtlSeconds > 0) {
            $cacheItem->set($item);
            $cacheItem->expiresAfter($this->defaultTtlSeconds);
            $this->cachePool->save($cacheItem);
        }

        return $item;
    }

    private function buildCacheKey(Region $region, int $id, Locale $locale): string
    {
        $localeKey = strtolower($locale->value);

        $identity = sprintf('%s:%s:%d', strtolower($region->value), $localeKey, $id);
        $hash = substr(hash('sha256', $identity), 0, 48);

        return sprintf(
            'uw.item.%s.%s',
            strtolower($region->value),
            $hash,
        );
    }
}
