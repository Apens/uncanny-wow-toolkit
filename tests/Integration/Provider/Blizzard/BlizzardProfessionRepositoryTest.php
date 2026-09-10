<?php

declare(strict_types=1);

namespace UncannyWoW\Tests\Integration\Provider\Blizzard;

use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientInterface;
use UncannyWoW\Core\Config\ClientConfiguration;
use UncannyWoW\Core\Domain\Enum\Locale;
use UncannyWoW\Core\Domain\Enum\Region;
use UncannyWoW\Core\Domain\Exception\ResourceNotFoundException;
use UncannyWoW\Provider\Blizzard\Auth\OAuthTokenProvider;
use UncannyWoW\Provider\Blizzard\Client\BlizzardApiClient;
use UncannyWoW\Provider\Blizzard\Hydrator\ProfessionHydrator;
use UncannyWoW\Provider\Blizzard\Repository\BlizzardProfessionRepository;

final class BlizzardProfessionRepositoryTest extends TestCase
{
    private Psr17Factory $factory;
    private ClientConfiguration $config;

    protected function setUp(): void
    {
        $this->factory = new Psr17Factory();
        $this->config = new ClientConfiguration('test-client-id', 'test-client-secret', Region::EU, Locale::FR_FR);
    }

    public function testGetByIdSuccess200(): void
    {
        $tokenFixture = file_get_contents(__DIR__ . '/../../../Fixtures/Blizzard/oauth_token_200.json');
        self::assertIsString($tokenFixture);

        $professionPayload = json_encode([
            'id' => 171,
            'name' => 'Alchemy',
            'description' => 'The art of brewing potions.',
            'skill_tiers' => [
                [
                    'id' => 2822,
                    'name' => 'Khaz Algar Alchemy',
                ],
            ],
        ], JSON_THROW_ON_ERROR);

        /** @var list<string> $requestedUrls */
        $requestedUrls = [];
        $httpClient = $this->createMock(ClientInterface::class);
        $httpClient->expects(self::exactly(2))
            ->method('sendRequest')
            ->willReturnCallback(function ($request) use (&$requestedUrls, $tokenFixture, $professionPayload) {
                $requestedUrls[] = (string) $request->getUri();
                if (count($requestedUrls) === 1) {
                    return new Response(200, ['Content-Type' => 'application/json'], $tokenFixture);
                }
                return new Response(200, ['Content-Type' => 'application/json'], $professionPayload);
            });

        $tokenProvider = new OAuthTokenProvider($this->config, $httpClient, $this->factory, $this->factory);
        $apiClient = new BlizzardApiClient($this->config, $httpClient, $this->factory, $tokenProvider);
        $hydrator = new ProfessionHydrator();
        $repository = new BlizzardProfessionRepository($apiClient, $hydrator);

        $profession = $repository->getById(Region::EU, 171, Locale::FR_FR);

        self::assertSame(171, $profession->id);
        self::assertSame('Alchemy', $profession->name);
        self::assertSame('The art of brewing potions.', $profession->description);
        self::assertCount(1, $profession->skillTiers);
        self::assertSame(2822, $profession->skillTiers[0]->id);

        self::assertCount(2, $requestedUrls);
        self::assertStringContainsString('/data/wow/profession/171', $requestedUrls[1]);
        self::assertStringContainsString('namespace=static-eu', $requestedUrls[1]);
        self::assertStringContainsString('locale=fr_FR', $requestedUrls[1]);
    }

    public function testGetSkillTierSuccess200(): void
    {
        $tokenFixture = file_get_contents(__DIR__ . '/../../../Fixtures/Blizzard/oauth_token_200.json');
        self::assertIsString($tokenFixture);

        $skillTierPayload = json_encode([
            'id' => 2822,
            'name' => 'Khaz Algar Alchemy',
            'minimum_skill_level' => 1,
            'maximum_skill_level' => 100,
            'categories' => [
                [
                    'name' => 'Potions',
                    'recipes' => [
                        [
                            'id' => 37000,
                            'name' => 'Algari Healing Potion',
                        ],
                    ],
                ],
            ],
        ], JSON_THROW_ON_ERROR);

        /** @var list<string> $requestedUrls */
        $requestedUrls = [];
        $httpClient = $this->createMock(ClientInterface::class);
        $httpClient->expects(self::exactly(2))
            ->method('sendRequest')
            ->willReturnCallback(function ($request) use (&$requestedUrls, $tokenFixture, $skillTierPayload) {
                $requestedUrls[] = (string) $request->getUri();
                if (count($requestedUrls) === 1) {
                    return new Response(200, ['Content-Type' => 'application/json'], $tokenFixture);
                }
                return new Response(200, ['Content-Type' => 'application/json'], $skillTierPayload);
            });

        $tokenProvider = new OAuthTokenProvider($this->config, $httpClient, $this->factory, $this->factory);
        $apiClient = new BlizzardApiClient($this->config, $httpClient, $this->factory, $tokenProvider);
        $hydrator = new ProfessionHydrator();
        $repository = new BlizzardProfessionRepository($apiClient, $hydrator);

        $skillTier = $repository->getSkillTier(Region::EU, 171, 2822, Locale::FR_FR);

        self::assertSame(2822, $skillTier->id);
        self::assertSame(171, $skillTier->professionId);
        self::assertSame('Khaz Algar Alchemy', $skillTier->name);
        self::assertSame(1, $skillTier->minimumSkillLevel);
        self::assertSame(100, $skillTier->maximumSkillLevel);
        self::assertCount(1, $skillTier->categories);
        self::assertSame([37000], $skillTier->getAllRecipeIds());

        self::assertCount(2, $requestedUrls);
        self::assertStringContainsString('/data/wow/profession/171/skill-tier/2822', $requestedUrls[1]);
        self::assertStringContainsString('namespace=static-eu', $requestedUrls[1]);
        self::assertStringContainsString('locale=fr_FR', $requestedUrls[1]);
    }

    public function testGetById404ThrowsResourceNotFoundException(): void
    {
        $tokenFixture = file_get_contents(__DIR__ . '/../../../Fixtures/Blizzard/oauth_token_200.json');
        self::assertIsString($tokenFixture);

        $notFoundPayload = json_encode(['code' => 404, 'type' => 'BLZWEBAPI00000404', 'detail' => 'Not Found'], JSON_THROW_ON_ERROR);

        $httpClient = $this->createMock(ClientInterface::class);
        $httpClient->expects(self::exactly(2))
            ->method('sendRequest')
            ->willReturnOnConsecutiveCalls(
                new Response(200, ['Content-Type' => 'application/json'], $tokenFixture),
                new Response(404, ['Content-Type' => 'application/json'], $notFoundPayload),
            );

        $tokenProvider = new OAuthTokenProvider($this->config, $httpClient, $this->factory, $this->factory);
        $apiClient = new BlizzardApiClient($this->config, $httpClient, $this->factory, $tokenProvider);
        $hydrator = new ProfessionHydrator();
        $repository = new BlizzardProfessionRepository($apiClient, $hydrator);

        try {
            $repository->getById(Region::EU, 99999999, Locale::FR_FR);
            self::fail('Expected ResourceNotFoundException was not thrown.');
        } catch (ResourceNotFoundException $e) {
            self::assertSame('profession', $e->getResourceType());
            self::assertSame('eu:99999999', $e->getIdentifier());
        }
    }

    public function testGetSkillTier404ThrowsResourceNotFoundException(): void
    {
        $tokenFixture = file_get_contents(__DIR__ . '/../../../Fixtures/Blizzard/oauth_token_200.json');
        self::assertIsString($tokenFixture);

        $notFoundPayload = json_encode(['code' => 404, 'type' => 'BLZWEBAPI00000404', 'detail' => 'Not Found'], JSON_THROW_ON_ERROR);

        $httpClient = $this->createMock(ClientInterface::class);
        $httpClient->expects(self::exactly(2))
            ->method('sendRequest')
            ->willReturnOnConsecutiveCalls(
                new Response(200, ['Content-Type' => 'application/json'], $tokenFixture),
                new Response(404, ['Content-Type' => 'application/json'], $notFoundPayload),
            );

        $tokenProvider = new OAuthTokenProvider($this->config, $httpClient, $this->factory, $this->factory);
        $apiClient = new BlizzardApiClient($this->config, $httpClient, $this->factory, $tokenProvider);
        $hydrator = new ProfessionHydrator();
        $repository = new BlizzardProfessionRepository($apiClient, $hydrator);

        try {
            $repository->getSkillTier(Region::EU, 171, 99999999, Locale::FR_FR);
            self::fail('Expected ResourceNotFoundException was not thrown.');
        } catch (ResourceNotFoundException $e) {
            self::assertSame('skill_tier', $e->getResourceType());
            self::assertSame('eu:171:99999999', $e->getIdentifier());
        }
    }

    public function testInvalidArgumentsThrow(): void
    {
        $httpClient = $this->createStub(ClientInterface::class);
        $tokenProvider = new OAuthTokenProvider($this->config, $httpClient, $this->factory, $this->factory);
        $apiClient = new BlizzardApiClient($this->config, $httpClient, $this->factory, $tokenProvider);
        $hydrator = new ProfessionHydrator();
        $repository = new BlizzardProfessionRepository($apiClient, $hydrator);

        $this->expectException(\InvalidArgumentException::class);
        $repository->getById(Region::EU, -1, Locale::FR_FR);
    }
}
