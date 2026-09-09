Uncanny-WoW Toolkit - Architecture

Architecture Version: 1.0
Package: uncanny-wow/toolkit
Namespace: UncannyWoW\
Minimum PHP Version: PHP >= 8.3
License: MIT
Status: Approved

1. Executive Summary

The Uncanny-WoW Toolkit (uncanny-wow/toolkit) is a standalone, domain-oriented PHP library providing a clean, strongly typed API for World of Warcraft data. The toolkit abstracts low-level provider mechanics—such as OAuth Client Credentials flows, access token acquisition and renewal, HTTP endpoint resolution, API namespaces, and raw JSON payload decoding—behind an intuitive PHP domain model.

Application developers consume high-level domain concepts (CharacterProfile, Realm, Faction) through a clean, fluent entry point:

$character = $wow->characters()->profile(
    realmSlug: 'la-croisade-écarlate',
    name: 'norigosa'
);

Core Identity

Package: uncanny-wow/toolkit

Namespace: UncannyWoW\

Language Support: Minimum PHP >= 8.3

Framework Coupling: Zero runtime framework dependencies.

Interoperability: Standard PSR interfaces (PSR-18 HTTP Client, PSR-17 Request/Stream Factories, PSR-6 Cache Pool).

Testing Standard: 100% offline automated test suite relying on PHPUnit, static JSON fixtures, and mocked PSR-18 transport.

2. Final Architectural Decisions

Before implementation begins, we freeze the following core decisions:

DECIDED NOW

Single Composer Package: uncanny-wow/toolkit with root namespace UncannyWoW\.

PHP Minimum Version: PHP >= 8.3 in composer.json. CI tests PHP 8.3 alongside newer compatible PHP versions (e.g. 8.4, 8.5).

HTTP Transport Standard: Direct usage of Psr\Http\Client\ClientInterface (PSR-18), Psr\Http\Message\RequestFactoryInterface (PSR-17), and Psr\Http\Message\StreamFactoryInterface (PSR-17). No custom transport interface.

Primary Cache Standard: Psr\Cache\CacheItemPoolInterface (PSR-6).

Testing Framework: PHPUnit (latest stable release supporting PHP 8.3).

Code Formatting: PHP-CS-Fixer configured for PER Coding Style 2.0. Configuration file: .php-cs-fixer.dist.php.

Static Analysis: PHPStan Level 9 configured to detect strict typing issues and reduce runtime error classes.

First Feature Scope: Character Profile vertical slice (EU / la-croisade-ecarlate / norigosa as the primary integration test case).

Authentication Architecture: OAuth endpoint resolution is strictly separated from regional World of Warcraft API hostnames.

Client Secret Security: Sensitive credentials wrapped in encapsulated configuration getters. Zero credentials in public properties, debug dumps, exceptions, or logs.

DEFERRED

Symfony Bundle Bridge: Deferred until post-Milestone 2 review.

Secondary Data Providers (Raider.IO, Economy external APIs): Deferred.

Additional WoW Domain APIs (Guilds, Items, Auction House, Mythic+): Deferred until after Milestone 2 architecture review.

Multi-Package / Monorepo Extraction: Deferred until public 1.0 stabilization.

3. Corrections Made From Version 2

Subject

Version 2 Proposal

Final Version 3 Proposal

Rationale

PHP Strategy

Implied raising minimum PHP version over time

Fixed minimum to PHP >= 8.3. CI matrix tests 8.3 + newer compatible PHP versions (8.4, 8.5)

Prevents premature breakage for PHP 8.3 users. Minimum version increases only via deliberate major release decisions.

OAuth Endpoint

Derived from region (https://<region>.battle.net)

Separated OAuth endpoint resolution from regional API hostnames (eu.api.blizzard.com)

Follows official Blizzard OAuth architecture and eliminates outdated regional OAuth assumptions.

PSR-17 Interfaces

Required only RequestFactoryInterface

Added StreamFactoryInterface

Necessary for creating POST request bodies cleanly (e.g., OAuth client credentials payload).

Client Secret Security

Public property $clientSecret

Encapsulated secret accessor method; excluded from debug context and exception context

Prevents accidental leak of sensitive credentials via var_dump, logs, or stack trace outputs.

Code Style File

Referenced phpcs.xml.dist

Standardized on .php-cs-fixer.dist.php

Resolves formatting tool file naming inconsistency.

Test Framework

"Pest or PHPUnit"

PHPUnit explicitly selected

Industry standard for reusable libraries with minimal abstraction overhead and native CI integration.

Static Analysis Wording

Claimed "guarantees 100% type safety"

"PHPStan Level 9 is used to detect strict typing issues and reduce a large class of runtime errors."

Realistic, professional engineering terminology.

Fixtures

Hard-coded illustrative data

Realistic Blizzard JSON payloads captured or structured according to official Blizzard schemas

Guarantees test accuracy against true provider contract behavior.

Domain Over-Modeling

Created Value Objects for every scalar (e.g. Level)

Level remains a typed int; Value Objects used only for identity, validation, and domain invariants

Strictly adheres to YAGNI. Avoids wrapping every primitive in a class.

4. Architectural Principles

YAGNI ("Simple now, extractable later"): Abstractions are created only when a concrete use case demands them.

Controlled Technical Debt: Technical debt is managed deliberately, documented, contained, and regularly reviewed.

Dependency Inversion: Core logic depends strictly on domain abstractions and standard PHP interfaces (PSR-18, PSR-17, PSR-6).

Provider Isolation: Blizzard API hostnames, OAuth flows, namespace parameters (profile-eu), and raw JSON structures remain 100% encapsulated within UncannyWoW\Provider\Blizzard.

Type Safety & Immutability: All domain entities, value objects, and configuration classes use declare(strict_types=1), native PHP 8.3 types, and readonly properties.

5. Recommended Architecture

The toolkit implements a lean Hexagonal Architecture (Ports and Adapters).

                      +------------------------------------------+
                      |         Application Layer (Consumer)     |
                      |   (Website, Road to 7M, CLI, Bots, etc.) |
                      +--------------------+---------------------+
                                           |
                                           v
                      +--------------------+---------------------+
                      |       UncannyWoWClient Facade            |
                      |       ($wow->characters()->profile...)   |
                      +--------------------+---------------------+
                                           |
                                           v
+------------------------------------------+------------------------------------------+
| CORE LAYER (UncannyWoW\Core)                                                        |
|                                                                                     |
|   +--------------------------+  +--------------------------+  +-----------------+   |
|   | Domain Models            |  | Value Objects            |  | Exceptions      |   |
|   | (CharacterProfile)       |  | (CharacterId, Realm...)  |  | (NotFound...)   |   |
|   +--------------------------+  +--------------------------+  +-----------------+   |
|                                                                                     |
|   +-----------------------------------------------------------------------------+   |
|   | Ports (Contracts)                                                           |   |
|   | - CharacterRepositoryInterface                                              |   |
|   | - External PSR Ports: Psr\Http\Client\ClientInterface (PSR-18)               |   |
|   |                       Psr\Http\Message\RequestFactoryInterface (PSR-17)    |   |
|   |                       Psr\Http\Message\StreamFactoryInterface (PSR-17)     |   |
|   |                       Psr\Cache\CacheItemPoolInterface (PSR-6)              |   |
|   +-----------------------------------------------------------------------------+   |
+------------------------------------------+------------------------------------------+
                                           ^
                                           | Implements Ports
+------------------------------------------+------------------------------------------+
| BLIZZARD PROVIDER LAYER (UncannyWoW\Provider\Blizzard)                              |
|                                                                                     |
|   +------------------------------------+   +------------------------------------+   |
|   | OAuth & Endpoint Resolution        |   | Hydration & Repositories           |   |
|   | - OAuthTokenProvider               |   | - CharacterProfileHydrator         |   |
|   | - BlizzardApiClient                |   | - BlizzardCharacterRepository      |   |
|   | - BlizzardOAuthEndpointResolver    |   | - CachedCharacterRepository        |   |
|   | - BlizzardApiEndpointResolver      |   |                                    |   |
|   +------------------------------------+   +------------------------------------+   |
+-------------------------------------------------------------------------------------+

6. Minimal Initial Directory Structure

For Milestone 0 and Milestone 1 (Character Profile Vertical Slice), only the following lean directory structure will be created:

uncanny-wow-toolkit/
├── src/
│   ├── Core/
│   │   ├── Config/
│   │   │   └── ClientConfiguration.php
│   │   ├── Contract/
│   │   │   └── Repository/
│   │   │       └── CharacterRepositoryInterface.php
│   │   ├── Domain/
│   │   │   ├── Enum/
│   │   │   │   ├── Faction.php
│   │   │   │   ├── Locale.php
│   │   │   │   └── Region.php
│   │   │   ├── Model/
│   │   │   │   └── Character/
│   │   │   │       ├── CharacterId.php
│   │   │   │       ├── CharacterProfile.php
│   │   │   │       ├── PlayableClass.php
│   │   │   │       └── Realm.php
│   │   │   └── Exception/
│   │   │       ├── AuthenticationException.php
│   │   │       ├── ConfigurationException.php
│   │   │       ├── InvalidResponseException.php
│   │   │       ├── NetworkException.php
│   │   │       ├── ProviderUnavailableException.php
│   │   │       ├── RateLimitExceededException.php
│   │   │       ├── ResourceNotFoundException.php
│   │   │       └── UncannyWoWException.php
│   │   └── UncannyWoWClient.php
│   └── Provider/
│       └── Blizzard/
│           ├── Auth/
│           │   ├── BlizzardOAuthEndpointResolver.php
│           │   └── OAuthTokenProvider.php
│           ├── Client/
│           │   ├── BlizzardApiClient.php
│           │   └── BlizzardApiEndpointResolver.php
│           ├── Hydrator/
│           │   └── CharacterProfileHydrator.php
│           └── Repository/
│               ├── BlizzardCharacterRepository.php
│               └── CachedCharacterRepository.php
├── tests/
│   ├── Fixtures/
│   │   └── Blizzard/
│   │       ├── character_profile_200.json
│   │       ├── character_profile_404.json
│   │       └── oauth_token_200.json
│   ├── Integration/
│   │   └── Provider/Blizzard/
│   │       └── BlizzardCharacterRepositoryTest.php
│   └── Unit/
│       ├── Core/
│       │   └── Domain/Model/CharacterIdTest.php
│       └── Provider/Blizzard/
│           ├── Auth/OAuthTokenProviderTest.php
│           └── Hydrator/CharacterProfileHydratorTest.php
├── .editorconfig
├── .gitignore
├── .php-cs-fixer.dist.php
├── phpstan.neon.dist
└── phpunit.xml.dist

7. Long-Term Target Structure

In future milestones, the directory structure may evolve into:

uncanny-wow-toolkit/
├── src/
│   ├── Core/
│   │   ├── Contract/
│   │   │   └── Repository/
│   │   │       ├── AuctionHouseRepositoryInterface.php
│   │   │       ├── CharacterRepositoryInterface.php
│   │   │       ├── GuildRepositoryInterface.php
│   │   │       ├── ItemRepositoryInterface.php
│   │   │       └── RealmRepositoryInterface.php
│   │   ├── Domain/
│   │   │   ├── Model/
│   │   │   │   ├── Auction/
│   │   │   │   ├── Character/
│   │   │   │   ├── Guild/
│   │   │   │   ├── Item/
│   │   │   │   └── Realm/
│   └── Provider/
│       ├── Blizzard/
│       │   ├── Hydrator/
│       │   └── Repository/
│       └── External/                       (e.g. RaiderIO, Economy Providers)
│   └── Bridge/
│       └── Symfony/                            (Scaffolding added ONLY when Core is stable)
│           ├── DependencyInjection/
│           └── UncannyWoWBundle.php

8. First Vertical Slice Architecture

Milestone 1 validates the complete production-quality path for:

Integration Validation Target: Region: EU, Realm: la-croisade-ecarlate, Character: norigosa.

Note: The code remains 100% generic for any valid character. Norigosa serves as the concrete fixture/test target.

Public Developer Experience:

use UncannyWoW\Core\Config\ClientConfiguration;
use UncannyWoW\Core\Domain\Enum\Region;
use UncannyWoW\Core\UncannyWoWClient;

$config = new ClientConfiguration(
    clientId: 'my-blizzard-client-id',
    clientSecret: 'my-blizzard-client-secret',
    region: Region::EU
);

$wow = UncannyWoWClient::create(
    config: $config,
    httpClient: $httpClient,        // Psr\Http\Client\ClientInterface
    requestFactory: $requestFactory,// Psr\Http\Message\RequestFactoryInterface
    streamFactory: $streamFactory,  // Psr\Http\Message\StreamFactoryInterface
    cachePool: $cachePool           // Psr\Cache\CacheItemPoolInterface (optional)
);

$character = $wow->characters()->profile(
    realmSlug: 'la-croisade-écarlate',
    name: 'norigosa'
);

echo $character->name;             // "Norigosa"
echo $character->level;            // typed int from the provider response
echo $character->realm->name;      // provider-derived realm name
echo $character->playableClass->name; // provider-derived class name

9. Detailed Character Profile Data Flow

[ Application Code ]
       |
       | 1. Calls ->profile(realmSlug: 'la-croisade-écarlate', name: 'norigosa')
       v
[ UncannyWoWClient Facade ]
       |
       | 2. Delegates to CharacterRepositoryInterface
       v
[ CachedCharacterRepository (Decorator) ]
       |
       | 3. Checks PSR-6 CachePool for key "uncanny_wow.blizzard.eu.profile.la_croisade_ecarlate.norigosa"
       |    +-- IF HIT: Returns cached CharacterProfile instance.
       |    +-- IF MISS: Proceeds to underlying repository.
       v
[ BlizzardCharacterRepository ]
       |
       | 4. Requests Bearer Token from OAuthTokenProvider
       |    - OAuthTokenProvider checks PSR-6 cache (or memory fallback) for valid token.
       |    - If token expired/missing, uses BlizzardOAuthEndpointResolver + StreamFactory to POST /oauth/token.
       |
       | 5. Uses BlizzardApiEndpointResolver to resolve URL:
       |    - Host: https://eu.api.blizzard.com
       |    - Path: /profile/wow/character/la-croisade-ecarlate/norigosa
       |    - Headers: Authorization: Bearer <token>
       |    - Query Params: namespace=profile-eu, locale=fr_FR
       v
[ BlizzardApiClient ]
       |
       | 6. Executes HTTP GET via Psr\Http\Client\ClientInterface (PSR-18)
       v
[ Response Validation ]
       |
       | 7. Validates HTTP Status Code:
       |    - 200 OK: Send body array to CharacterProfileHydrator
       |    - 404 Not Found: Throw ResourceNotFoundException (resource: character, id: norigosa)
       |    - 429 Rate Limit: Throw RateLimitExceededException (with retryAfterSeconds)
       |    - 5xx Server Error: Throw ProviderUnavailableException
       v
[ CharacterProfileHydrator ]
       |
       | 8. Hydrates JSON array into immutable CharacterProfile entity and Value Objects (Realm, CharacterId, PlayableClass)
       v
[ CachedCharacterRepository ]
       |
       | 9. Stores CharacterProfile in PSR-6 CachePool with configured TTL (default 15 minutes)
       v
[ Application Code ]
       |
       | 10. Receives strongly typed CharacterProfile domain instance

10. Domain Model & Value Object Strategy

We adhere strictly to pragmatic domain modeling to avoid over-engineering.

10.1 Classification Rules

Classification

Purpose

Vertical Slice Examples

Type / Mutability

Entity

Domain object with explicit composite identity.

CharacterProfile

readonly class

Value Object

Object defining identity, canonicalization, or domain invariants.

CharacterId, Realm, PlayableClass

readonly class

Enum

Backed domain scalars.

Region, Locale, Faction

Backed Enum

Primitive Scalar

Simple attributes without domain behaviors.

level (int), name (string)

Scalar types

10.2 Domain Invariant Example (CharacterId)

namespace UncannyWoW\Core\Domain\Model\Character;

use UncannyWoW\Core\Domain\Enum\Region;

readonly class CharacterId
{
    public string $realmSlug;
    public string $characterName;

    public function __construct(
        public Region $region,
        string $realmSlug,
        string $characterName
    ) {
        $this->realmSlug = strtolower(trim($realmSlug));
        $this->characterName = strtolower(trim($characterName));

        if ($this->realmSlug === '') {
            throw new \InvalidArgumentException('Realm slug cannot be empty.');
        }
        if ($this->characterName === '') {
            throw new \InvalidArgumentException('Character name cannot be empty.');
        }
    }
}

11. HTTP & Stream Strategy

Standard PSR Dependencies

The toolkit depends strictly on standard PSR interfaces:

Psr\Http\Client\ClientInterface (PSR-18)

Psr\Http\Message\RequestFactoryInterface (PSR-17)

Psr\Http\Message\StreamFactoryInterface (PSR-17)

namespace UncannyWoW\Provider\Blizzard\Client;

use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;

readonly class BlizzardApiClient
{
    public function __construct(
        private ClientInterface $httpClient,
        private RequestFactoryInterface $requestFactory,
        private StreamFactoryInterface $streamFactory
    ) {}
}

12. Authentication & Client Secret Security Strategy

12.1 Endpoint Separation

OAuth authentication endpoint resolution is decoupled from regional WoW API hostnames:

OAuth Host: BlizzardOAuthEndpointResolver::resolve() (e.g. https://oauth.battle.net/token or configured OAuth URL).

WoW API Host: BlizzardApiEndpointResolver::resolve(Region $region) (e.g. https://eu.api.blizzard.com).

12.2 Credential Encapsulation

To protect sensitive credentials from accidental leakage in debug context, logging, serialization, or exception traces:

namespace UncannyWoW\Core\Config;

use UncannyWoW\Core\Domain\Enum\Region;

readonly class ClientConfiguration
{
    private string $clientSecret;

    public function __construct(
        public string $clientId,
        #[\SensitiveParameter] string $clientSecret,
        public Region $region = Region::EU,
        public int $defaultProfileTtlSeconds = 900
    ) {
        $this->clientSecret = $clientSecret;
    }

    public function getClientSecret(): string
    {
        return $this->clientSecret;
    }

    public function __debugInfo(): array
    {
        return [
            'clientId' => $this->clientId,
            'clientSecret' => '***REDACTED***',
            'region' => $this->region,
        ];
    }
}

13. Cache Strategy

13.1 Standard Abstraction: PSR-6 (CacheItemPoolInterface)

Primary caching relies on Psr\Cache\CacheItemPoolInterface.

13.2 TTL Configuration & Overrides

Character Profile Default TTL: 15 minutes (900 seconds).

Consuming Application Overrides: Applications can override default TTL via ClientConfiguration or per-request parameters ($options['ttl']).

OAuth Token TTL: Automatically derived from Blizzard's expires_in response minus a 60-second safety buffer.

13.3 Cache Fallback & Memory Behavior

If no PSR-6 CacheItemPoolInterface is provided by the consuming application:

Character Profiles: Caching is bypassed; requests fetch directly from the Blizzard API.

OAuth Tokens: The OAuthTokenProvider maintains an in-memory token fallback for the duration of the PHP process request lifecycle, preventing repeated OAuth POST requests within a single execution.

14. Error Handling Strategy

All toolkit-level exceptions implement a single marker interface (UncannyWoWException) and extend an appropriate native PHP exception base class.

UncannyWoWException (Marker Interface)
├── ConfigurationException          (Invalid Client ID, empty credentials, invalid region)
├── AuthenticationException         (OAuth token failure, 401 Unauthorized)
├── ResourceNotFoundException       (Character or Realm not found, 404 Not Found)
├── RateLimitExceededException      (Quota exceeded, 429 Too Many Requests; includes $retryAfterSeconds)
├── ProviderUnavailableException    (Blizzard downtime, 500/502/503/504)
├── InvalidResponseException        (Malformed JSON payload or schema contract mismatch)
└── NetworkException                (Socket/timeout connection failures from PSR-18 client)

Security Rule for Exceptions

Exceptions MAY contain contextual information (resourceType, identifier, retryAfterSeconds), but MUST NEVER include sensitive credentials (clientSecret, accessToken, Authorization headers).

15. Testing Strategy & Realistic Fixtures

15.1 Framework Selection: PHPUnit

We select PHPUnit (latest stable release supporting PHP 8.3).

Why PHPUnit?

Ecosystem standard for reusable PHP libraries.

Zero extra abstraction layers or DSL overhead.

Direct native integration with Composer, static analysis, and CI tooling.

15.2 Realistic Fixtures & Offline Testing

100% Offline CI: Automated test runs execute offline using PSR-18 mock responses.

Realistic Data: Fixtures (character_profile_200.json, character_profile_404.json, oauth_token_200.json) represent real Blizzard API response payloads. Tests validate against data present in fixtures.

16. CI Quality Gates & Coverage Strategy

16.1 Single GitHub Actions Workflow (.github/workflows/ci.yml)

Composer Validation: composer validate --strict

Code Style Check: vendor/bin/php-cs-fixer check --dry-run

Static Analysis: vendor/bin/phpstan analyse (Level 9)

Automated Unit & Integration Tests: vendor/bin/phpunit across PHP matrix.

16.2 PHP Matrix Strategy

Minimum Supported Version: PHP 8.3

CI Test Matrix: PHP 8.3, PHP 8.4, PHP 8.5 (as supported by dependencies).

16.3 Coverage Policy

Code coverage is a diagnostic tool, not an arbitrary numerical gate. CI blocks PRs on failure of essential behavioral boundaries (OAuth acquisition, token caching/expiry, HTTP 200/404/429/5xx responses, malformed JSON, network errors, cache hits/misses, domain invariants).

17. Milestone Strategy

Milestone 0 - Project Foundation Scope

Objective: Setup basic project layout, tools, and quality gates.

Files to create in Milestone 0:

composer.json (uncanny-wow/toolkit, php >=8.3)

.editorconfig, .gitignore

.php-cs-fixer.dist.php

phpstan.neon.dist

phpunit.xml.dist

.github/workflows/ci.yml

Directory skeleton: src/ and tests/

Note: No domain or provider classes will be created during Milestone 0.

Milestone 1 - Character Profile Vertical Slice

Objective: Build and test the complete Character Profile capability.

Implement ClientConfiguration (with credential security).

Implement Region, Locale, Faction enums.

Implement UncannyWoWException hierarchy.

Implement BlizzardOAuthEndpointResolver and OAuthTokenProvider (with token caching & memory fallback).

Implement BlizzardApiEndpointResolver and BlizzardApiClient.

Implement CharacterId, Realm, PlayableClass, and CharacterProfile domain models.

Implement CharacterProfileHydrator using character_profile_200.json.

Implement BlizzardCharacterRepository and CachedCharacterRepository.

Implement UncannyWoWClient public facade.

Implement PHPUnit test suite covering 200, 404, 429, 5xx, and OAuth token expiration scenarios.

Milestone 2 - Mandatory Architecture Review

Status: COMPLETED (Verdict: APPROVE WITH MINOR REFACTORING)

Objective: Review architectural fit before expanding to additional domains.

Key Findings & Approved Refactoring:

1. Canonical Realm Slug Parameter ($realmSlug):
   To eliminate ambiguity between human-readable display names (e.g. "La Croisade écarlate") and canonical Blizzard slugs, all public service methods and repository contracts representing realm slugs explicitly name the parameter $realmSlug (e.g., CharacterService::profile(string $realmSlug, ...), CharacterRepositoryInterface::findProfile(Region $region, string $realmSlug, ...)). Domain entities retain $character->realm for the Realm model.

2. Unicode Realm Slug Normalization & URL Encoding:
   Blizzard canonical realm slugs can contain non-ASCII Unicode characters (e.g. "la-croisade-écarlate"). The toolkit deterministically normalizes realm slugs using mb_strtolower(..., 'UTF-8') and encodes URL path segments via rawurlencode() (producing e.g. /profile/wow/character/la-croisade-%C3%A9carlate/norigosa). Whitespace is strictly disallowed in realm slugs.

3. Service Instance Memoization on Client Facade:
   Domain accessor methods on UncannyWoWClient (e.g., $wow->characters()) memoize the underlying service instance internally using the null-coalescing assignment operator (??=), ensuring that repeated invocations reuse the same service instance rather than creating new objects on every call.

4. ClientConfiguration Simplicity Maintained:
   The single, unified ClientConfiguration is retained for now. Deferring the split into distinct transport, authentication, or profile configurations avoids unnecessary premature abstraction (YAGNI) while the domain footprint remains focused.

5. 100% Offline CI & Live Smoke Testing Separation:
   Automated test suites and CI workflows remain completely offline, relying on mock PSR-18 HTTP transports and realistic static fixtures. Live API verification against Blizzard endpoints is reserved for manual/local smoke tests with real credentials and is excluded from CI.

6. Next Vertical Slice Roadmap:
   Following this cleanup, the next vertical slice will be Realm lookup ($wow->realms()), providing realm status, slug resolution, and connected realm discovery prior to expanding into Guilds, Items, or Auction House domains.

18. Decisions Summary

DECIDED NOW

Package: uncanny-wow/toolkit

Namespace: UncannyWoW\

PHP Version: PHP >= 8.3 (Tested against 8.3, 8.4, 8.5)

Standard Interfaces: PSR-18 Client, PSR-17 Request/Stream Factories, PSR-6 Cache Pool

Test Engine: PHPUnit

Formatter: PHP-CS-Fixer (.php-cs-fixer.dist.php)

Static Analysis: PHPStan Level 9

Scope: Character Profile Vertical Slice (Milestone 1 completed, Milestone 2 review approved)

API Parameter Naming: Canonical $realmSlug for slug inputs across services and repositories

Service Memoization: Domain services memoized on UncannyWoWClient facade

DEFERRED

Symfony Bridge (UncannyWoW\Bridge\Symfony)

Secondary Data Providers

Additional WoW Domain APIs (Guilds, Items, Auction House, Mythic+)

ClientConfiguration Splitting (deferred until additional domain needs emerge)

Monorepo Sub-Package Splitting

19. Final Recommendation

This architecture freezes the foundational decisions for the first implementation phase. It establishes credential security rules, isolates OAuth endpoint resolution, enforces pragmatic domain modeling, and incorporates the findings of the Milestone 2 Architecture Review.

Implementation of future slices will proceed with Realm lookup.
