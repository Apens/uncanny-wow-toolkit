<?php

declare(strict_types=1);

namespace UncannyWoW\Core\Service;

use UncannyWoW\Core\Config\ClientConfiguration;
use UncannyWoW\Core\Contract\Repository\RealmRepositoryInterface;
use UncannyWoW\Core\Domain\Enum\Locale;
use UncannyWoW\Core\Domain\Enum\Region;
use UncannyWoW\Core\Domain\Model\Realm\Realm;

class RealmService
{
    public function __construct(
        private readonly RealmRepositoryInterface $realmRepository,
        private readonly ClientConfiguration $config,
    ) {}

    /**
     * Retrieve a realm by its canonical Blizzard realm slug (e.g. 'la-croisade-écarlate').
     *
     * @param string $slug Canonical Blizzard realm slug. Whitespace is not permitted.
     * @param Region|null $region Optional override for the target region (defaults to client configured region).
     * @param Locale|null $locale Optional override for the target locale (defaults to client configured locale).
     */
    public function get(string $slug, ?Region $region = null, ?Locale $locale = null): Realm
    {
        $targetRegion = $region ?? $this->config->region;
        $targetLocale = $locale ?? $this->config->defaultLocale;

        return $this->realmRepository->getBySlug($targetRegion, $slug, $targetLocale);
    }

    /**
     * Search and discover realms by realm display name (e.g. 'La Croisade écarlate').
     *
     * @param string $name Human-readable realm display name.
     * @param Region|null $region Optional override for the target region (defaults to client configured region).
     * @param Locale|null $locale Optional override for the target locale (defaults to client configured locale).
     * @return list<Realm>
     */
    public function search(string $name, ?Region $region = null, ?Locale $locale = null): array
    {
        $targetRegion = $region ?? $this->config->region;
        $targetLocale = $locale ?? $this->config->defaultLocale;

        return $this->realmRepository->searchByName($targetRegion, $name, $targetLocale);
    }
}
