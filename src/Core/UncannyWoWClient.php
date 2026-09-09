<?php

declare(strict_types=1);

namespace UncannyWoW\Core;

use Psr\Cache\CacheItemPoolInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use UncannyWoW\Core\Config\ClientConfiguration;
use UncannyWoW\Core\Contract\Repository\AuctionHouseRepositoryInterface;
use UncannyWoW\Core\Contract\Repository\CharacterRepositoryInterface;
use UncannyWoW\Core\Contract\Repository\ConnectedRealmRepositoryInterface;
use UncannyWoW\Core\Contract\Repository\ItemRepositoryInterface;
use UncannyWoW\Core\Contract\Repository\RealmRepositoryInterface;
use UncannyWoW\Core\Domain\Enum\Locale;
use UncannyWoW\Core\Domain\Enum\Region;
use UncannyWoW\Core\Domain\Exception\ConfigurationException;
use UncannyWoW\Core\Service\AuctionHouseService;
use UncannyWoW\Core\Service\CharacterService;
use UncannyWoW\Core\Service\ConnectedRealmService;
use UncannyWoW\Core\Service\ItemService;
use UncannyWoW\Core\Service\RealmService;
use UncannyWoW\Provider\Blizzard\Auth\OAuthTokenProvider;
use UncannyWoW\Provider\Blizzard\Client\BlizzardApiClient;
use UncannyWoW\Provider\Blizzard\Hydrator\AuctionHouseHydrator;
use UncannyWoW\Provider\Blizzard\Hydrator\CharacterProfileHydrator;
use UncannyWoW\Provider\Blizzard\Hydrator\ConnectedRealmHydrator;
use UncannyWoW\Provider\Blizzard\Hydrator\ItemHydrator;
use UncannyWoW\Provider\Blizzard\Hydrator\RealmHydrator;
use UncannyWoW\Provider\Blizzard\Repository\BlizzardAuctionHouseRepository;
use UncannyWoW\Provider\Blizzard\Repository\BlizzardCharacterRepository;
use UncannyWoW\Provider\Blizzard\Repository\BlizzardConnectedRealmRepository;
use UncannyWoW\Provider\Blizzard\Repository\BlizzardItemRepository;
use UncannyWoW\Provider\Blizzard\Repository\BlizzardRealmRepository;
use UncannyWoW\Provider\Blizzard\Repository\CachedCharacterRepository;
use UncannyWoW\Provider\Blizzard\Repository\CachedConnectedRealmRepository;
use UncannyWoW\Provider\Blizzard\Repository\CachedItemRepository;
use UncannyWoW\Provider\Blizzard\Repository\CachedRealmRepository;

class UncannyWoWClient
{
    private ?CharacterService $characterService = null;
    private ?RealmService $realmService = null;
    private ?ItemService $itemService = null;
    private ?ConnectedRealmService $connectedRealmService = null;
    private ?AuctionHouseService $auctionHouseService = null;

    public function __construct(
        private readonly CharacterRepositoryInterface $characterRepository,
        private readonly ClientConfiguration $config,
        private readonly ?RealmRepositoryInterface $realmRepository = null,
        private readonly ?ItemRepositoryInterface $itemRepository = null,
        private readonly ?ConnectedRealmRepositoryInterface $connectedRealmRepository = null,
        private readonly ?AuctionHouseRepositoryInterface $auctionHouseRepository = null,
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

        $connectedRealmHydrator = new ConnectedRealmHydrator($realmHydrator);
        $blizzardConnectedRealmRepository = new BlizzardConnectedRealmRepository($apiClient, $connectedRealmHydrator);

        $cachedConnectedRealmRepository = new CachedConnectedRealmRepository(
            innerRepository: $blizzardConnectedRealmRepository,
            cachePool: $cachePool,
            defaultTtlSeconds: 86400,
        );

        $auctionHouseHydrator = new AuctionHouseHydrator();
        $blizzardAuctionHouseRepository = new BlizzardAuctionHouseRepository($apiClient, $auctionHouseHydrator);

        return new self(
            $cachedRepository,
            $config,
            $cachedRealmRepository,
            $cachedItemRepository,
            $cachedConnectedRealmRepository,
            $blizzardAuctionHouseRepository,
        );
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

    public function connectedRealms(): ConnectedRealmService
    {
        if ($this->connectedRealmRepository === null) {
            throw new ConfigurationException('Connected realm repository is not configured on this client.');
        }

        return $this->connectedRealmService ??= new ConnectedRealmService($this->connectedRealmRepository, $this->config);
    }

    public function auctionHouse(): AuctionHouseService
    {
        if ($this->auctionHouseRepository === null) {
            throw new ConfigurationException('Auction house repository is not configured on this client.');
        }

        return $this->auctionHouseService ??= new AuctionHouseService($this->auctionHouseRepository, $this->config);
    }
}
