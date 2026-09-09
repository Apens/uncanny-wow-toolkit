Uncanny-WoW Toolkit




A modern PHP toolkit for building applications around World of Warcraft.

Write Warcraft code, not integration code.

Uncanny-WoW Toolkit aims to make it easier to build PHP applications that use World of Warcraft data without having to deal directly with authentication flows, raw HTTP endpoints, response parsing, or repetitive integration code.

The project is currently in early development.

Quick start

```php
use Nyholm\Psr7\Factory\Psr17Factory;
use Symfony\Component\HttpClient\Psr18Client;
use UncannyWoW\Core\Domain\Enum\Region;
use UncannyWoW\Core\UncannyWoWClient;

$psr17Factory = new Psr17Factory();
$httpClient = new Psr18Client();

$wow = UncannyWoWClient::create(
    clientId: 'my-blizzard-client-id',
    clientSecret: 'my-blizzard-client-secret',
    httpClient: $httpClient,
    requestFactory: $psr17Factory,
    streamFactory: $psr17Factory,
    defaultRegion: Region::EU,
);

// Discover a realm and its canonical slug by display name
$matches = $wow->realms()->search(name: 'La Croisade écarlate');
$realm = $matches[0];
echo $realm->slug;                    // "la-croisade-écarlate"

// Or fetch realm details directly by canonical slug
$realm = $wow->realms()->get(slug: 'la-croisade-écarlate');
echo $realm->name;                    // "La Croisade écarlate"
echo $realm->timezone;                // "Europe/Paris"

$character = $wow->characters()->profile(
    realmSlug: $realm->slug,
    name: 'norigosa',
);

echo $character->name;                // "Norigosa"
echo $character->level;               // 80
echo $character->realm->name;         // "La Croisade écarlate"
echo $character->playableClass->name; // "Mage"
echo $character->faction->value;      // "HORDE"
```

Goals

Provide a clean, object-oriented PHP API for World of Warcraft data.

Hide low-level HTTP and OAuth complexity whenever possible.

Use strict typing and predictable domain objects.

Provide caching support to reduce unnecessary external API calls.

Remain usable outside Symfony while integrating cleanly with it.

Build reusable foundations for websites, dashboards, bots, CLI tools, and other WoW-related applications.

Planned capabilities

The toolkit is intended to grow progressively around reusable modules such as:

Blizzard API integration

Characters

Guilds

Items

Raids and encounters

Dungeons and Mythic+

Auction House and economy data

External market-data providers

Caching and synchronization helpers

Only generic and reusable functionality belongs in the toolkit. Application-specific business logic should stay in the applications that consume it.

Project status

Uncanny-WoW Toolkit is at the beginning of its development.

The first milestone is to establish a reliable foundation for connecting to Blizzard's APIs, handling authentication, retrieving data, and exposing it through a clean PHP interface.

Vision

The broader goals and design principles of the project will be documented in VISION.md.

License

This project is licensed under the MIT License.
