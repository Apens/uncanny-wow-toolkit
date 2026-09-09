<?php

declare(strict_types=1);

namespace UncannyWoW\Provider\Blizzard\Client;

use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use UncannyWoW\Core\Config\ClientConfiguration;
use UncannyWoW\Core\Domain\Enum\Locale;
use UncannyWoW\Core\Domain\Enum\Region;
use UncannyWoW\Core\Domain\Exception\AuthenticationException;
use UncannyWoW\Core\Domain\Exception\InvalidResponseException;
use UncannyWoW\Core\Domain\Exception\NetworkException;
use UncannyWoW\Core\Domain\Exception\ProviderUnavailableException;
use UncannyWoW\Core\Domain\Exception\RateLimitExceededException;
use UncannyWoW\Core\Domain\Exception\ResourceNotFoundException;
use UncannyWoW\Provider\Blizzard\Auth\OAuthTokenProvider;

class BlizzardApiClient
{
    public function __construct(
        private readonly ClientConfiguration $config,
        private readonly ClientInterface $httpClient,
        private readonly RequestFactoryInterface $requestFactory,
        private readonly OAuthTokenProvider $tokenProvider,
    ) {}

    /**
     * @param array<string, string> $queryParams
     * @return array<string, mixed>
     */
    public function get(
        Region $region,
        string $path,
        array $queryParams = [],
        ?Locale $locale = null,
        ?string $namespace = null,
        ?string $resourceTypeForNotFound = null,
        ?string $identifierForNotFound = null,
    ): array {
        $token = $this->tokenProvider->getAccessToken();
        $host = BlizzardApiEndpointResolver::resolveHost($region);

        $mergedQueryParams = array_merge([
            'namespace' => $namespace ?? BlizzardApiEndpointResolver::resolveProfileNamespace($region),
            'locale' => ($locale ?? $this->config->defaultLocale)->value,
        ], $queryParams);

        $url = sprintf('%s%s?%s', $host, $path, http_build_query($mergedQueryParams));

        $request = $this->requestFactory->createRequest('GET', $url)
            ->withHeader('Authorization', 'Bearer ' . $token);

        try {
            $response = $this->httpClient->sendRequest($request);
        } catch (ClientExceptionInterface $e) {
            throw new NetworkException('Network error while communicating with Blizzard API.', 0, $e);
        }

        $statusCode = $response->getStatusCode();

        if ($statusCode === 401) {
            throw new AuthenticationException('Blizzard API request failed with 401 Unauthorized.');
        }

        if ($statusCode === 404) {
            throw new ResourceNotFoundException(
                $resourceTypeForNotFound ?? 'resource',
                $identifierForNotFound ?? $path,
                sprintf('Resource "%s" was not found on Blizzard API.', $identifierForNotFound ?? $path),
            );
        }

        if ($statusCode === 429) {
            $retryAfterHeader = $response->getHeaderLine('Retry-After');
            $retryAfterSeconds = is_numeric($retryAfterHeader) ? (int) $retryAfterHeader : null;
            throw new RateLimitExceededException($retryAfterSeconds, 'Blizzard API rate limit exceeded.');
        }

        if ($statusCode >= 500 && $statusCode <= 599) {
            throw new ProviderUnavailableException(sprintf('Blizzard API unavailable with status %d.', $statusCode));
        }

        if ($statusCode !== 200) {
            throw new ProviderUnavailableException(sprintf('Unexpected HTTP status %d received from Blizzard API.', $statusCode));
        }

        $bodyRaw = (string) $response->getBody();

        try {
            /** @var mixed $data */
            $data = json_decode($bodyRaw, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new InvalidResponseException('Malformed JSON payload received from Blizzard API.', 0, $e);
        }

        if (!is_array($data)) {
            throw new InvalidResponseException('Expected JSON object/array from Blizzard API response.');
        }

        /** @var array<string, mixed> $data */
        return $data;
    }
}
