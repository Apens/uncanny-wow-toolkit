<?php

declare(strict_types=1);

namespace UncannyWoW\Tests\Integration;

use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientInterface;
use UncannyWoW\Core\Domain\Enum\Locale;
use UncannyWoW\Core\Domain\Enum\Region;
use UncannyWoW\Core\UncannyWoWClient;

final class UncannyWoWClientConnectedRealmIntegrationTest extends TestCase
{
    public function testEndToEndConnectedRealmGet(): void
    {
        $factory = new Psr17Factory();
        $tokenFixture = file_get_contents(__DIR__ . '/../Fixtures/Blizzard/oauth_token_200.json');
        $connectedRealmFixture = file_get_contents(__DIR__ . '/../Fixtures/Blizzard/connected_realm_200.json');
        self::assertIsString($tokenFixture);
        self::assertIsString($connectedRealmFixture);

        $httpClient = $this->createMock(ClientInterface::class);
        $httpClient->expects(self::exactly(2))
            ->method('sendRequest')
            ->willReturnOnConsecutiveCalls(
                new Response(200, ['Content-Type' => 'application/json'], $tokenFixture),
                new Response(200, ['Content-Type' => 'application/json'], $connectedRealmFixture),
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

        $connectedRealm = $wow->connectedRealms()->get(id: 1127);

        self::assertSame(1127, $connectedRealm->id);
        self::assertCount(7, $connectedRealm->realms);

        self::assertSame(1127, $connectedRealm->realms[0]->id);
        self::assertSame('confrérie-du-thorium', $connectedRealm->realms[0]->slug);
        self::assertSame('Confrérie du Thorium', $connectedRealm->realms[0]->name);
        self::assertSame('Français', $connectedRealm->realms[0]->category);
        self::assertSame(Locale::FR_FR, $connectedRealm->realms[0]->locale);
        self::assertSame('Europe/Paris', $connectedRealm->realms[0]->timezone);
        self::assertSame(1127, $connectedRealm->realms[0]->connectedRealmId);

        self::assertSame(1086, $connectedRealm->realms[1]->id);
        self::assertSame('la-croisade-écarlate', $connectedRealm->realms[1]->slug);
        self::assertSame('La Croisade écarlate', $connectedRealm->realms[1]->name);
        self::assertSame(1127, $connectedRealm->realms[1]->connectedRealmId);

        self::assertSame(1337, $connectedRealm->realms[2]->id);
        self::assertSame('culte-de-la-rive-noire', $connectedRealm->realms[2]->slug);
        self::assertSame('Culte de la Rive noire', $connectedRealm->realms[2]->name);
        self::assertSame(1127, $connectedRealm->realms[2]->connectedRealmId);

        self::assertSame(647, $connectedRealm->realms[3]->id);
        self::assertSame('les-sentinelles', $connectedRealm->realms[3]->slug);
        self::assertSame(1127, $connectedRealm->realms[3]->connectedRealmId);

        self::assertSame(537, $connectedRealm->realms[4]->id);
        self::assertSame('kirin-tor', $connectedRealm->realms[4]->slug);
        self::assertSame(1127, $connectedRealm->realms[4]->connectedRealmId);

        self::assertSame(1626, $connectedRealm->realms[5]->id);
        self::assertSame('les-clairvoyants', $connectedRealm->realms[5]->slug);
        self::assertSame(1127, $connectedRealm->realms[5]->connectedRealmId);

        self::assertSame(644, $connectedRealm->realms[6]->id);
        self::assertSame('conseil-des-ombres', $connectedRealm->realms[6]->slug);
        self::assertSame(1127, $connectedRealm->realms[6]->connectedRealmId);
    }

    public function testConnectedRealmsMemoization(): void
    {
        $factory = new Psr17Factory();
        $httpClient = $this->createStub(ClientInterface::class);

        $wow = UncannyWoWClient::create(
            clientId: 'test',
            clientSecret: 'test',
            httpClient: $httpClient,
            requestFactory: $factory,
            streamFactory: $factory,
        );

        $service1 = $wow->connectedRealms();
        $service2 = $wow->connectedRealms();

        self::assertSame($service1, $service2);
    }
}
