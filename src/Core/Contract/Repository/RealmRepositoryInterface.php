<?php

declare(strict_types=1);

namespace UncannyWoW\Core\Contract\Repository;

use UncannyWoW\Core\Domain\Enum\Locale;
use UncannyWoW\Core\Domain\Enum\Region;
use UncannyWoW\Core\Domain\Model\Realm\Realm;

interface RealmRepositoryInterface
{
    public function getBySlug(Region $region, string $slug, ?Locale $locale = null): Realm;

    /**
     * @return list<Realm>
     */
    public function searchByName(Region $region, string $name, ?Locale $locale = null): array;
}
