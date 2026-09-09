<?php

declare(strict_types=1);

namespace UncannyWoW\Provider\Blizzard\Auth;

final class BlizzardOAuthEndpointResolver
{
    private const DEFAULT_OAUTH_HOST = 'https://oauth.battle.net/token';

    public static function resolve(?string $customOAuthUrl = null): string
    {
        if ($customOAuthUrl !== null && trim($customOAuthUrl) !== '') {
            return $customOAuthUrl;
        }

        return self::DEFAULT_OAUTH_HOST;
    }
}
