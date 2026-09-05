Uncanny-WoW Toolkit




A modern PHP toolkit for building applications around World of Warcraft.

Write Warcraft code, not integration code.

Uncanny-WoW Toolkit aims to make it easier to build PHP applications that use World of Warcraft data without having to deal directly with authentication flows, raw HTTP endpoints, response parsing, or repetitive integration code.

The project is currently in early development.

Quick start

The public API is still being designed, but the goal is to make common operations simple and expressive:

<?php

use UncannyWoW\Blizzard\BlizzardClient;

$client = new BlizzardClient(/* configuration */);

$character = $client->characters()->profile(
    realm: 'la-croisade-ecarlate',
    name: 'norigosa',
);

echo $character->name();

The final API may evolve while the project is under active development.

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
