<?php

declare(strict_types=1);

namespace UncannyWoW\Core\Config;

use UncannyWoW\Core\Domain\Enum\Locale;
use UncannyWoW\Core\Domain\Enum\Region;
use UncannyWoW\Core\Domain\Exception\ConfigurationException;

readonly class ClientConfiguration
{
    private string $clientSecret;

    public function __construct(
        public string $clientId,
        #[\SensitiveParameter]
        string $clientSecret,
        public Region $region = Region::EU,
        public Locale $defaultLocale = Locale::FR_FR,
        public int $defaultProfileTtlSeconds = 900,
    ) {
        if (trim($this->clientId) === '') {
            throw new ConfigurationException('Client ID cannot be empty.');
        }
        if (trim($clientSecret) === '') {
            throw new ConfigurationException('Client Secret cannot be empty.');
        }

        $this->clientSecret = $clientSecret;
    }

    public function getClientSecret(): string
    {
        return $this->clientSecret;
    }

    /**
     * Prevent sensitive client secret from leaking in var_dump / print_r output.
     *
     * @return array<string, mixed>
     */
    public function __debugInfo(): array
    {
        return [
            'clientId' => $this->clientId,
            'clientSecret' => '***REDACTED***',
            'region' => $this->region,
            'defaultLocale' => $this->defaultLocale,
            'defaultProfileTtlSeconds' => $this->defaultProfileTtlSeconds,
        ];
    }

    /**
     * Prevent serialization to protect sensitive credentials from accidental leakage.
     *
     * @return array<string, mixed>
     */
    public function __serialize(): array
    {
        throw new \LogicException('ClientConfiguration cannot be serialized.');
    }

    /**
     * Prevent unserialization.
     *
     * @param array<string, mixed> $data
     */
    public function __unserialize(array $data): void
    {
        throw new \LogicException('ClientConfiguration cannot be unserialized.');
    }
}
