<?php

declare(strict_types=1);

namespace UncannyWoW\Provider\Blizzard\Hydrator;

use UncannyWoW\Core\Domain\Exception\InvalidResponseException;
use UncannyWoW\Core\Domain\Model\ConnectedRealm\ConnectedRealm;

final class ConnectedRealmHydrator
{
    public function __construct(
        private readonly RealmHydrator $realmHydrator = new RealmHydrator(),
    ) {}

    /**
     * @param array<string, mixed> $data
     */
    public function hydrate(array $data): ConnectedRealm
    {
        if (!isset($data['id']) || !is_int($data['id'])) {
            throw new InvalidResponseException('Missing or invalid "id" in connected realm payload.');
        }

        $connectedRealmId = $data['id'];

        if (!isset($data['realms']) || !is_array($data['realms']) || $data['realms'] === []) {
            throw new InvalidResponseException('Missing or empty "realms" in connected realm payload.');
        }

        $realms = [];
        foreach ($data['realms'] as $index => $realmData) {
            if (!is_array($realmData)) {
                throw new InvalidResponseException(sprintf('Invalid realm data at index %s in connected realm payload.', (string) $index));
            }

            /** @var array<string, mixed> $realmData */
            $realms[] = $this->realmHydrator->hydrate($realmData, fallbackConnectedRealmId: $connectedRealmId);
        }

        return new ConnectedRealm(
            id: $connectedRealmId,
            realms: $realms,
        );
    }
}
