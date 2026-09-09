<?php

declare(strict_types=1);

namespace UncannyWoW\Provider\Blizzard\Client;

use UncannyWoW\Core\Domain\Enum\Region;

final class BlizzardApiEndpointResolver
{
    public static function resolveHost(Region $region): string
    {
        return sprintf('https://%s.api.blizzard.com', $region->value);
    }

    public static function resolveProfileNamespace(Region $region): string
    {
        return sprintf('profile-%s', $region->value);
    }

    public static function resolveDynamicNamespace(Region $region): string
    {
        return sprintf('dynamic-%s', $region->value);
    }

    public static function resolveStaticNamespace(Region $region): string
    {
        return sprintf('static-%s', $region->value);
    }
}
