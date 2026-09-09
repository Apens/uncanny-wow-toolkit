<?php

declare(strict_types=1);

namespace UncannyWoW\Tests\Integration;

use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientInterface;
use UncannyWoW\Core\Config\ClientConfiguration;
use UncannyWoW\Core\Contract\Repository\CharacterRepositoryInterface;
use UncannyWoW\Core\Domain\Enum\Locale;
use UncannyWoW\Core\Domain\Enum\Region;
use UncannyWoW\Core\Domain\Exception\ConfigurationException;
use UncannyWoW\Core\UncannyWoWClient;

final class UncannyWoWClientRealmIntegrationTest extends TestCase
{
    public function testEndToEndRealmGetAndSearch(): void
    {
        $factory = new Psr17Factory();
        $tokenFixture = file_get_contents(__DIR__ . '/../Fixtures/Blizzard/oauth_token_200.json');
        $realmFixture = file_get_contents(__DIR__ . '/../Fixtures/Blizzard/realm_200.json');
        $searchFixture = file_get_contents(__DIR__ . '/../Fixtures/Blizzard/realm_search_200.json');
        self::assertIsString($tokenFixture);
        self::assertIsString($realmFixture);
        self::assertIsString($searchFixture);

        $httpClient = $this->createMock(ClientInterface::class);
        $httpClient->expects(self::exactly(3))
            ->method('sendRequest')
            ->willReturnOnConsecutiveCalls(
                new Response(200, ['Content-Type' => 'application/json'], $tokenFixture),
                new Response(200, ['Content-Type' => 'application/json'], $realmFixture),
                new Response(200, ['Content-Type' => 'application/json'], $searchFixture),
            );

        $wow = UncannyWoWClient::create(
            clientId: 'integration-client-id',
            clientSecret: 'integration-client-secret',
            httpClient: $httpClient,
            requestFactory: $factory,
            streamFactory: $factory,
            defaultRegion: Region::EU,
            defaultLocale: Locale::FR_FR,
        );

        // 1. Test get by slug
        $realm = $wow->realms()->get(slug: 'la-croisade-écarlate');
        self::assertSame(1086, $realm->id);
        self::assertSame('la-croisade-écarlate', $realm->slug);
        self::assertSame('La Croisade écarlate', $realm->name);
        self::assertSame('French', $realm->category);
        self::assertSame(Locale::FR_FR, $realm->locale);
        self::assertSame('Europe/Paris', $realm->timezone);
        self::assertSame(1086, $realm->connectedRealmId);

        // 2. Test search by name
        $searchResults = $wow->realms()->search(name: 'La Croisade écarlate');
        self::assertCount(1, $searchResults);
        self::assertSame(1086, $searchResults[0]->id);
        self::assertSame('la-croisade-écarlate', $searchResults[0]->slug);
        self::assertSame('La Croisade écarlate', $searchResults[0]->name);
    }

    public function testRealmsMemoization(): void
    {
        $factory = new Psr17Factory();
        $httpClient = $this->createStub(ClientInterface::class);

        $wow = UncannyWoWClient::create(
            clientId: 'integration-client-id',
            clientSecret: 'integration-client-secret',
            httpClient: $httpClient,
            requestFactory: $factory,
            streamFactory: $factory,
            defaultRegion: Region::EU,
        );

        self::assertSame($wow->realms(), $wow->realms());
    }

    public function testRealmsThrowsWhenRealmRepositoryNotConfigured(): void
    {
        $charRepo = $this->createStub(CharacterRepositoryInterface::class);
        $config = new ClientConfiguration('id', 'secret', Region::EU);

        $clientWithoutRealmRepo = new UncannyWoWClient($charRepo, $config);

        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage('Realm repository is not configured on this client.');

        $clientWithoutRealmRepo->realms();
    }
}
