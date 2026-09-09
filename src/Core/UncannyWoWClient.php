<?php

declare(strict_types=1);

namespace UncannyWoW\Core;

use Psr\Cache\CacheItemPoolInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use UncannyWoW\Core\Config\ClientConfiguration;
use UncannyWoW\Core\Contract\Repository\CharacterRepositoryInterface;
use UncannyWoW\Core\Domain\Enum\Locale;
use UncannyWoW\Core\Domain\Enum\Region;
use UncannyWoW\Core\Service\CharacterService;
use UncannyWoW\Provider\Blizzard\Auth\OAuthTokenProvider;
use UncannyWoW\Provider\Blizzard\Client\BlizzardApiClient;
use UncannyWoW\Provider\Blizzard\Hydrator\CharacterProfileHydrator;
use UncannyWoW\Provider\Blizzard\Repository\BlizzardCharacterRepository;
use UncannyWoW\Provider\Blizzard\Repository\CachedCharacterRepository;

class UncannyWoWClient
{
    private ?CharacterService $characterService = null;

    public function __construct(
        private readonly CharacterRepositoryInterface $characterRepository,
        private readonly ClientConfiguration $config,
    ) {}

    public static function create(
        string $clientId,
        #[\SensitiveParameter]
        string $clientSecret,
        ClientInterface $httpClient,
        RequestFactoryInterface $requestFactory,
        StreamFactoryInterface $streamFactory,
        Region $defaultRegion = Region::EU,
        Locale $defaultLocale = Locale::FR_FR,
        ?CacheItemPoolInterface $cachePool = null,
        ?string $customOAuthUrl = null,
        int $defaultProfileTtlSeconds = 900,
    ): self {
        $config = new ClientConfiguration(
            clientId: $clientId,
            clientSecret: $clientSecret,
            region: $defaultRegion,
            defaultLocale: $defaultLocale,
            defaultProfileTtlSeconds: $defaultProfileTtlSeconds,
        );

        return self::createFromConfiguration(
            config: $config,
            httpClient: $httpClient,
            requestFactory: $requestFactory,
            streamFactory: $streamFactory,
            cachePool: $cachePool,
            customOAuthUrl: $customOAuthUrl,
        );
    }

    public static function createFromConfiguration(
        ClientConfiguration $config,
        ClientInterface $httpClient,
        RequestFactoryInterface $requestFactory,
        StreamFactoryInterface $streamFactory,
        ?CacheItemPoolInterface $cachePool = null,
        ?string $customOAuthUrl = null,
    ): self {
        $tokenProvider = new OAuthTokenProvider(
            config: $config,
            httpClient: $httpClient,
            requestFactory: $requestFactory,
            streamFactory: $streamFactory,
            cachePool: $cachePool,
            customOAuthUrl: $customOAuthUrl,
        );

        $apiClient = new BlizzardApiClient(
            config: $config,
            httpClient: $httpClient,
            requestFactory: $requestFactory,
            tokenProvider: $tokenProvider,
        );

        $hydrator = new CharacterProfileHydrator();
        $blizzardRepository = new BlizzardCharacterRepository($apiClient, $hydrator);

        $cachedRepository = new CachedCharacterRepository(
            innerRepository: $blizzardRepository,
            cachePool: $cachePool,
            defaultTtlSeconds: $config->defaultProfileTtlSeconds,
        );

        return new self($cachedRepository, $config);
    }

    public function characters(): CharacterService
    {
        return $this->characterService ??= new CharacterService($this->characterRepository, $this->config);
    }
}
