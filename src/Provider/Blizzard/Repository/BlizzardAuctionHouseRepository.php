<?php

declare(strict_types=1);

namespace UncannyWoW\Provider\Blizzard\Repository;

use JsonMachine\Items;
use JsonMachine\JsonDecoder\ExtJsonDecoder;
use Psr\Http\Message\StreamInterface;
use UncannyWoW\Core\Contract\Repository\AuctionHouseRepositoryInterface;
use UncannyWoW\Core\Domain\Enum\Region;
use UncannyWoW\Core\Domain\Exception\InvalidResponseException;
use UncannyWoW\Core\Domain\Model\AuctionHouse\Auction;
use UncannyWoW\Core\Domain\Model\AuctionHouse\AuctionHouseSnapshot;
use UncannyWoW\Core\Domain\Model\AuctionHouse\CommodityAuction;
use UncannyWoW\Core\Domain\Model\AuctionHouse\CommodityMarketSnapshot;
use UncannyWoW\Provider\Blizzard\Client\BlizzardApiClient;
use UncannyWoW\Provider\Blizzard\Client\BlizzardApiEndpointResolver;
use UncannyWoW\Provider\Blizzard\Hydrator\AuctionHouseHydrator;

class BlizzardAuctionHouseRepository implements AuctionHouseRepositoryInterface
{
    public function __construct(
        private readonly BlizzardApiClient $apiClient,
        private readonly AuctionHouseHydrator $hydrator,
    ) {}

    public function getAuctions(Region $region, int $connectedRealmId): AuctionHouseSnapshot
    {
        if ($connectedRealmId <= 0) {
            throw new \InvalidArgumentException(sprintf('Connected realm ID must be a positive integer, got %d.', $connectedRealmId));
        }

        $path = sprintf('/data/wow/connected-realm/%d/auctions', $connectedRealmId);

        $stream = $this->apiClient->getStream(
            region: $region,
            path: $path,
            namespace: BlizzardApiEndpointResolver::resolveDynamicNamespace($region),
            resourceTypeForNotFound: 'auction-house',
            identifierForNotFound: sprintf('%s:%d', $region->value, $connectedRealmId),
        );

        return new AuctionHouseSnapshot(
            connectedRealmId: $connectedRealmId,
            auctions: $this->lazyIterateAuctions($stream),
        );
    }

    public function getCommodities(Region $region): CommodityMarketSnapshot
    {
        $path = '/data/wow/auctions/commodities';

        $stream = $this->apiClient->getStream(
            region: $region,
            path: $path,
            namespace: BlizzardApiEndpointResolver::resolveDynamicNamespace($region),
            resourceTypeForNotFound: 'commodities',
            identifierForNotFound: $region->value,
        );

        return new CommodityMarketSnapshot(
            region: $region,
            auctions: $this->lazyIterateCommodities($stream),
        );
    }

    /**
     * @return \Generator<int, Auction>
     */
    private function lazyIterateAuctions(StreamInterface $stream): \Generator
    {
        $iterable = $this->createStreamChunkIterable($stream);
        $options = [
            'pointer' => '/auctions',
            'decoder' => new ExtJsonDecoder(true),
        ];

        try {
            $items = Items::fromIterable($iterable, $options);
            foreach ($items as $index => $row) {
                $rowKey = is_int($index) || is_string($index) ? $index : 0;
                if (!is_array($row)) {
                    throw new InvalidResponseException(sprintf('Invalid auction entry at index %s in auction house payload.', (string) $rowKey));
                }

                /** @var array<string, mixed> $row */
                yield $this->hydrator->hydrateAuction($row, $rowKey);
            }
        } catch (\JsonMachine\Exception\JsonMachineException $e) {
            throw new InvalidResponseException('Malformed JSON payload received from Blizzard API.', 0, $e);
        }
    }

    /**
     * @return \Generator<int, CommodityAuction>
     */
    private function lazyIterateCommodities(StreamInterface $stream): \Generator
    {
        $iterable = $this->createStreamChunkIterable($stream);
        $options = [
            'pointer' => '/auctions',
            'decoder' => new ExtJsonDecoder(true),
        ];

        try {
            $items = Items::fromIterable($iterable, $options);
            foreach ($items as $index => $row) {
                $rowKey = is_int($index) || is_string($index) ? $index : 0;
                if (!is_array($row)) {
                    throw new InvalidResponseException(sprintf('Invalid commodity entry at index %s in commodities payload.', (string) $rowKey));
                }

                /** @var array<string, mixed> $row */
                yield $this->hydrator->hydrateCommodityAuction($row, $rowKey);
            }
        } catch (\JsonMachine\Exception\JsonMachineException $e) {
            throw new InvalidResponseException('Malformed JSON payload received from Blizzard API.', 0, $e);
        }
    }

    /**
     * Adapt a PSR-7 StreamInterface into an iterable yielding string chunks for JsonMachine without loading the full body into memory.
     *
     * @return iterable<string>
     */
    private function createStreamChunkIterable(StreamInterface $stream, int $chunkSize = 65536): iterable
    {
        if ($stream->isSeekable()) {
            $stream->rewind();
        }

        while (!$stream->eof()) {
            $chunk = $stream->read($chunkSize);
            if ($chunk === '') {
                break;
            }
            yield $chunk;
        }
    }
}
