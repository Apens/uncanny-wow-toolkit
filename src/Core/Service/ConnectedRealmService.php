<?php

declare(strict_types=1);

namespace UncannyWoW\Core\Service;

use UncannyWoW\Core\Config\ClientConfiguration;
use UncannyWoW\Core\Contract\Repository\ConnectedRealmRepositoryInterface;
use UncannyWoW\Core\Domain\Enum\Locale;
use UncannyWoW\Core\Domain\Enum\Region;
use UncannyWoW\Core\Domain\Model\ConnectedRealm\ConnectedRealm;

class ConnectedRealmService
{
    public function __construct(
        private readonly ConnectedRealmRepositoryInterface $connectedRealmRepository,
        private readonly ClientConfiguration $config,
    ) {}

    /**
     * Retrieve a connected realm cluster by its unique numeric ID.
     *
     * @param int $id Positive integer connected realm ID.
     * @param Region|null $region Optional override for target region (defaults to client configured region).
     * @param Locale|null $locale Optional override for target locale (defaults to client configured locale).
     */
    public function get(int $id, ?Region $region = null, ?Locale $locale = null): ConnectedRealm
    {
        if ($id <= 0) {
            throw new \InvalidArgumentException(sprintf('Connected realm ID must be a positive integer, got %d.', $id));
        }

        $targetRegion = $region ?? $this->config->region;
        $targetLocale = $locale ?? $this->config->defaultLocale;

        return $this->connectedRealmRepository->getById($targetRegion, $id, $targetLocale);
    }
}
