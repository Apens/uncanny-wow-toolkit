<?php

declare(strict_types=1);

namespace UncannyWoW\Provider\Blizzard\Auth;

use Psr\Cache\CacheItemPoolInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use UncannyWoW\Core\Config\ClientConfiguration;
use UncannyWoW\Core\Domain\Exception\AuthenticationException;
use UncannyWoW\Core\Domain\Exception\InvalidResponseException;
use UncannyWoW\Core\Domain\Exception\NetworkException;

class OAuthTokenProvider
{
    private const SAFETY_BUFFER_SECONDS = 60;
    private ?string $inMemoryToken = null;
    private ?int $inMemoryExpiresAt = null;

    public function __construct(
        private readonly ClientConfiguration $config,
        private readonly ClientInterface $httpClient,
        private readonly RequestFactoryInterface $requestFactory,
        private readonly StreamFactoryInterface $streamFactory,
        private readonly ?CacheItemPoolInterface $cachePool = null,
        private readonly ?string $customOAuthUrl = null,
    ) {}

    public function getAccessToken(): string
    {
        $now = time();

        if ($this->inMemoryToken !== null && $this->inMemoryExpiresAt !== null && $this->inMemoryExpiresAt > $now) {
            return $this->inMemoryToken;
        }

        $cacheKey = $this->getCacheKey();
        if ($this->cachePool !== null) {
            $cacheItem = $this->cachePool->getItem($cacheKey);
            if ($cacheItem->isHit()) {
                /** @var string $token */
                $token = $cacheItem->get();
                $this->inMemoryToken = $token;
                return $token;
            }
        }

        $tokenData = $this->requestTokenFromBlizzard();
        $accessToken = $tokenData['access_token'];
        $expiresIn = $tokenData['expires_in'];

        $ttl = max(0, $expiresIn - self::SAFETY_BUFFER_SECONDS);
        $expiresAt = $now + $ttl;

        $this->inMemoryToken = $accessToken;
        $this->inMemoryExpiresAt = $expiresAt;

        if ($this->cachePool !== null && $ttl > 0) {
            $cacheItem = $this->cachePool->getItem($cacheKey);
            $cacheItem->set($accessToken);
            $cacheItem->expiresAfter($ttl);
            $this->cachePool->save($cacheItem);
        }

        return $accessToken;
    }

    /**
     * @return array{access_token: string, expires_in: int}
     */
    private function requestTokenFromBlizzard(): array
    {
        $url = BlizzardOAuthEndpointResolver::resolve($this->customOAuthUrl);
        $bodyContent = http_build_query(['grant_type' => 'client_credentials']);

        $credentials = base64_encode(sprintf('%s:%s', $this->config->clientId, $this->config->getClientSecret()));

        $request = $this->requestFactory->createRequest('POST', $url)
            ->withHeader('Authorization', 'Basic ' . $credentials)
            ->withHeader('Content-Type', 'application/x-www-form-urlencoded')
            ->withBody($this->streamFactory->createStream($bodyContent));

        try {
            $response = $this->httpClient->sendRequest($request);
        } catch (\Throwable $e) {
            throw new NetworkException('Network failure during OAuth token request.', 0, $e);
        }

        $statusCode = $response->getStatusCode();
        if ($statusCode !== 200) {
            throw new AuthenticationException(sprintf('Blizzard OAuth request failed with HTTP status %d.', $statusCode));
        }

        $bodyRaw = (string) $response->getBody();

        try {
            /** @var mixed $data */
            $data = json_decode($bodyRaw, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new InvalidResponseException('Malformed JSON response received from Blizzard OAuth endpoint.', 0, $e);
        }

        if (!is_array($data)) {
            throw new InvalidResponseException('Expected array response from Blizzard OAuth endpoint.');
        }

        if (!isset($data['access_token']) || !is_string($data['access_token']) || trim($data['access_token']) === '') {
            throw new InvalidResponseException('OAuth response missing valid "access_token" field.');
        }

        if (!isset($data['expires_in']) || !is_int($data['expires_in'])) {
            throw new InvalidResponseException('OAuth response missing valid "expires_in" integer field.');
        }

        return [
            'access_token' => $data['access_token'],
            'expires_in' => $data['expires_in'],
        ];
    }

    private function getCacheKey(): string
    {
        $url = BlizzardOAuthEndpointResolver::resolve($this->customOAuthUrl);
        $identity = $this->config->clientId . '@' . $url;
        $hash = substr(hash('sha256', $identity), 0, 48);

        return 'uw.oauth.' . $hash;
    }
}
