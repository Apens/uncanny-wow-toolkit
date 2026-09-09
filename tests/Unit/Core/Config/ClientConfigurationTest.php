<?php

declare(strict_types=1);

namespace UncannyWoW\Tests\Unit\Core\Config;

use PHPUnit\Framework\TestCase;
use UncannyWoW\Core\Config\ClientConfiguration;
use UncannyWoW\Core\Domain\Enum\Locale;
use UncannyWoW\Core\Domain\Enum\Region;
use UncannyWoW\Core\Domain\Exception\ConfigurationException;

final class ClientConfigurationTest extends TestCase
{
    public function testValidConfigurationInitialization(): void
    {
        $config = new ClientConfiguration(
            clientId: 'valid-client-id',
            clientSecret: 'valid-client-secret',
            region: Region::US,
            defaultLocale: Locale::EN_US,
            defaultProfileTtlSeconds: 600,
        );

        self::assertSame('valid-client-id', $config->clientId);
        self::assertSame('valid-client-secret', $config->getClientSecret());
        self::assertSame(Region::US, $config->region);
        self::assertSame(Locale::EN_US, $config->defaultLocale);
        self::assertSame(600, $config->defaultProfileTtlSeconds);
    }

    public function testEmptyClientIdThrowsConfigurationException(): void
    {
        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage('Client ID cannot be empty.');

        new ClientConfiguration(
            clientId: '   ',
            clientSecret: 'valid-secret',
        );
    }

    public function testEmptyClientSecretThrowsConfigurationException(): void
    {
        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage('Client Secret cannot be empty.');

        new ClientConfiguration(
            clientId: 'valid-id',
            clientSecret: '',
        );
    }

    public function testDebugInfoRedactsClientSecret(): void
    {
        $config = new ClientConfiguration(
            clientId: 'my-client-id',
            clientSecret: 'super-secret-key',
        );

        $debugInfo = $config->__debugInfo();

        self::assertSame('my-client-id', $debugInfo['clientId']);
        self::assertSame('***REDACTED***', $debugInfo['clientSecret']);
    }

    public function testSerializationIsBlocked(): void
    {
        $config = new ClientConfiguration(
            clientId: 'my-client-id',
            clientSecret: 'super-secret-key',
        );

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('ClientConfiguration cannot be serialized.');

        serialize($config);
    }

    public function testSensitiveParameterAttributeProtectsClientSecret(): void
    {
        $constructor = (new \ReflectionClass(ClientConfiguration::class))->getConstructor();
        self::assertNotNull($constructor);

        $params = $constructor->getParameters();
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
