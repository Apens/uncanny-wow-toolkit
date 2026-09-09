<?php

declare(strict_types=1);

namespace UncannyWoW\Provider\Blizzard\Hydrator;

use UncannyWoW\Core\Domain\Enum\Faction;
use UncannyWoW\Core\Domain\Enum\Region;
use UncannyWoW\Core\Domain\Exception\InvalidResponseException;
use UncannyWoW\Core\Domain\Model\Character\CharacterId;
use UncannyWoW\Core\Domain\Model\Character\CharacterProfile;
use UncannyWoW\Core\Domain\Model\Character\PlayableClass;
use UncannyWoW\Core\Domain\Model\Realm\Realm;

class CharacterProfileHydrator
{
    /**
     * @param array<string, mixed> $data
     */
    public function hydrate(Region $region, array $data): CharacterProfile
    {
        if (!isset($data['name']) || !is_string($data['name']) || trim($data['name']) === '') {
            throw new InvalidResponseException('Character profile response missing valid "name" field.');
        }

        if (!isset($data['level']) || !is_int($data['level'])) {
            throw new InvalidResponseException('Character profile response missing valid "level" integer field.');
        }

        if (!isset($data['realm']) || !is_array($data['realm'])) {
            throw new InvalidResponseException('Character profile response missing valid "realm" array field.');
        }

        $realmArray = $data['realm'];
        if (!isset($realmArray['id'], $realmArray['slug'], $realmArray['name']) || !is_int($realmArray['id']) || !is_string($realmArray['slug']) || !is_string($realmArray['name'])) {
            throw new InvalidResponseException('Character profile "realm" payload is incomplete or invalid.');
        }

        if (!isset($data['character_class']) || !is_array($data['character_class'])) {
            throw new InvalidResponseException('Character profile response missing valid "character_class" array field.');
        }

        $classArray = $data['character_class'];
        if (!isset($classArray['id'], $classArray['name']) || !is_int($classArray['id']) || !is_string($classArray['name'])) {
            throw new InvalidResponseException('Character profile "character_class" payload is incomplete or invalid.');
        }

        $faction = Faction::NEUTRAL;
        if (isset($data['faction']) && is_array($data['faction']) && isset($data['faction']['type']) && is_string($data['faction']['type'])) {
            $factionType = strtoupper(trim($data['faction']['type']));
            $faction = Faction::tryFrom($factionType) ?? Faction::NEUTRAL;
        }

        $characterId = new CharacterId($region, $realmArray['slug'], $data['name']);
        $realm = new Realm($realmArray['id'], $realmArray['slug'], $realmArray['name']);
        $playableClass = new PlayableClass($classArray['id'], $classArray['name']);

        return new CharacterProfile(
            id: $characterId,
            name: $data['name'],
            level: $data['level'],
            realm: $realm,
            playableClass: $playableClass,
            faction: $faction,
        );
    }
}
