<?php

declare(strict_types=1);

namespace UncannyWoW\Tests\Integration;

use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientInterface;
use UncannyWoW\Core\Config\ClientConfiguration;
use UncannyWoW\Core\Domain\Enum\Faction;
use UncannyWoW\Core\Domain\Enum\Region;
use UncannyWoW\Core\UncannyWoWClient;

final class UncannyWoWClientIntegrationTest extends TestCase
{
    public function testEndToEndCharacterProfileRetrieval(): void
    {
        $factory = new Psr17Factory();
        $config = new ClientConfiguration(
            clientId: 'integration-client-id',
            clientSecret: 'integration-client-secret',
            region: Region::EU,
        );

        $tokenFixture = file_get_contents(__DIR__ . '/../Fixtures/Blizzard/oauth_token_200.json');
        $profileFixture = file_get_contents(__DIR__ . '/../Fixtures/Blizzard/character_profile_200.json');
        self::assertIsString($tokenFixture);
        self::assertIsString($profileFixture);

        $httpClient = $this->createMock(ClientInterface::class);
        $httpClient->expects(self::exactly(2))
            ->method('sendRequest')
            ->willReturnOnConsecutiveCalls(
                new Response(200, ['Content-Type' => 'application/json'], $tokenFixture),
                new Response(200, ['Content-Type' => 'application/json'], $profileFixture),
            );

        $wow = UncannyWoWClient::create(
            clientId: 'integration-client-id',
            clientSecret: 'integration-client-secret',
            httpClient: $httpClient,
            requestFactory: $factory,
            streamFactory: $factory,
            defaultRegion: Region::EU,
        );

        $character = $wow->characters()->profile(
            realmSlug: 'la-croisade-ecarlate',
            name: 'norigosa',
        );

        self::assertSame('Norigosa', $character->name);
        self::assertSame(80, $character->level);
        self::assertSame(Region::EU, $character->id->region);
        self::assertSame('la-croisade-ecarlate', $character->id->realmSlug);
        self::assertSame('norigosa', $character->id->characterName);
        self::assertSame('La Croisade écarlate', $character->realm->name);
        self::assertSame('Mage', $character->playableClass->name);
        self::assertSame(Faction::HORDE, $character->faction);
    }

    public function testCharactersReturnsSameInstance(): void
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

        self::assertSame($wow->characters(), $wow->characters());
    }

    public function testOpportunitiesReturnsSameInstance(): void
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

        self::assertSame($wow->opportunities(), $wow->opportunities());
        self::assertInstanceOf(\UncannyWoW\Core\Service\OpportunityService::class, $wow->opportunities());
    }

    public function testSensitiveParameterAttributeOnClientCreate(): void
    {
        $method = new \ReflectionMethod(UncannyWoWClient::class, 'create');
        $params = $method->getParameters();
        $secretParam = null;
        foreach ($params as $param) {
            if ($param->getName() === 'clientSecret') {
                $secretParam = $param;
                break;
            }
        }

        self::assertNotNull($secretParam);
        self::assertNotEmpty($secretParam->getAttributes(\SensitiveParameter::class));
    }
}
