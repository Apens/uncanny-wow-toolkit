<?php

declare(strict_types=1);

namespace UncannyWoW\Core;

use Psr\Cache\CacheItemPoolInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use UncannyWoW\Core\Config\ClientConfiguration;
use UncannyWoW\Core\Contract\Repository\CharacterRepositoryInterface;
use UncannyWoW\Core\Contract\Repository\ItemRepositoryInterface;
use UncannyWoW\Core\Contract\Repository\RealmRepositoryInterface;
use UncannyWoW\Core\Domain\Enum\Locale;
use UncannyWoW\Core\Domain\Enum\Region;
use UncannyWoW\Core\Domain\Exception\ConfigurationException;
use UncannyWoW\Core\Service\CharacterService;
use UncannyWoW\Core\Service\ItemService;
use UncannyWoW\Core\Service\RealmService;
use UncannyWoW\Provider\Blizzard\Auth\OAuthTokenProvider;
use UncannyWoW\Provider\Blizzard\Client\BlizzardApiClient;
use UncannyWoW\Provider\Blizzard\Hydrator\CharacterProfileHydrator;
use UncannyWoW\Provider\Blizzard\Hydrator\ItemHydrator;
use UncannyWoW\Provider\Blizzard\Hydrator\RealmHydrator;
use UncannyWoW\Provider\Blizzard\Repository\BlizzardCharacterRepository;
use UncannyWoW\Provider\Blizzard\Repository\BlizzardItemRepository;
use UncannyWoW\Provider\Blizzard\Repository\BlizzardRealmRepository;
use UncannyWoW\Provider\Blizzard\Repository\CachedCharacterRepository;
use UncannyWoW\Provider\Blizzard\Repository\CachedItemRepository;
use UncannyWoW\Provider\Blizzard\Repository\CachedRealmRepository;

class UncannyWoWClient
{
    private ?CharacterService $characterService = null;
    private ?RealmService $realmService = null;
    private ?ItemService $itemService = null;

    public function __construct(
        private readonly CharacterRepositoryInterface $characterRepository,
        private readonly ClientConfiguration $config,
        private readonly ?RealmRepositoryInterface $realmRepository = null,
        private readonly ?ItemRepositoryInterface $itemRepository = null,
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

        $realmHydrator = new RealmHydrator();
        $blizzardRealmRepository = new BlizzardRealmRepository($apiClient, $realmHydrator, $config);

        $cachedRealmRepository = new CachedRealmRepository(
            innerRepository: $blizzardRealmRepository,
            cachePool: $cachePool,
            defaultTtlSeconds: 86400,
        );

        $itemHydrator = new ItemHydrator();
        $blizzardItemRepository = new BlizzardItemRepository($apiClient, $itemHydrator);

        $cachedItemRepository = new CachedItemRepository(
            innerRepository: $blizzardItemRepository,
            cachePool: $cachePool,
            defaultTtlSeconds: 86400,
        );

        return new self($cachedRepository, $config, $cachedRealmRepository, $cachedItemRepository);
    }

    public function characters(): CharacterService
    {
        return $this->characterService ??= new CharacterService($this->characterRepository, $this->config);
    }

    public function realms(): RealmService
    {
        if ($this->realmRepository === null) {
            throw new ConfigurationException('Realm repository is not configured on this client.');
        }

        return $this->realmService ??= new RealmService($this->realmRepository, $this->config);
    }

    public function items(): ItemService
    {
        if ($this->itemRepository === null) {
            throw new ConfigurationException('Item repository is not configured on this client.');
        }

        return $this->itemService ??= new ItemService($this->itemRepository, $this->config);
    }
}
