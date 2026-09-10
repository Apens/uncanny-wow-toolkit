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
│   │   │       ├── CharacterRepositoryInterface.php
│   │   │       └── RealmRepositoryInterface.php
│   │   ├── Domain/
│   │   │   ├── Enum/
│   │   │   │   ├── Faction.php
│   │   │   │   ├── Locale.php
│   │   │   │   └── Region.php
│   │   │   ├── Model/
│   │   │   │   ├── Character/
│   │   │   │   │   ├── CharacterId.php
│   │   │   │   │   ├── CharacterProfile.php
│   │   │   │   │   ├── PlayableClass.php
│   │   │   │   │   └── Realm.php
│   │   │   │   └── Realm/
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
│   │   ├── Service/
│   │   │   ├── CharacterService.php
│   │   │   └── RealmService.php
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
│           │   ├── CharacterProfileHydrator.php
│           │   └── RealmHydrator.php
│           └── Repository/
│               ├── BlizzardCharacterRepository.php
│               ├── BlizzardRealmRepository.php
│               ├── CachedCharacterRepository.php
│               └── CachedRealmRepository.php
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

6. Milestone 3 Roadmap Execution:
   The Realm vertical slice ($wow->realms()) implements canonical realm retrieval and discovery, dynamic-{region} namespace handling, and lightweight connected realm reference identification.

Milestone 3 - Realm Data & Realm Lookup Vertical Slice

Status: COMPLETED

Objective: Deliver typed Realm data and explicit realm discovery to solve the canonical slug dependency exposed by Character Profile.

Key Architectural Decisions Validated:

1. Dual Access Patterns (Get by Slug vs. Search Discovery):
   - Direct lookup by canonical slug: $wow->realms()->get(slug: 'la-croisade-écarlate')
   - Discovery by human-readable display name: $wow->realms()->search(name: 'La Croisade écarlate')
   Both return strongly typed Realm domain objects, preventing homemade slug guesswork or heuristic matching in consuming applications.

2. Dynamic Namespace Integration:
   Game Data endpoints require dynamic-{region} namespaces (e.g. dynamic-eu). Resolved cleanly via BlizzardApiEndpointResolver::resolveDynamicNamespace($region) without introducing custom transport abstractions.

3. Pragmatic Connected Realm Identification:
   Preserves a minimal ?int $connectedRealmId reference extracted from connected_realm.href without constructing speculative ConnectedRealm aggregates prematurely.

4. Deferred Realm Index Endpoint:
   The official Blizzard Realm Search API (/data/wow/search/realm) satisfies explicit discovery requirements directly. The Realm Index endpoint is deferred (YAGNI/KISS).

5. Cache Key Max Portability (PSR-6):
   - Slug lookup key: uw.realm.{region}.{hash48} (60 chars)
   - Search query key: uw.rs.{region}.{hash48} (57 chars)
   Both stay strictly <= 64 characters, use safe [a-z0-9_.] characters, and contain zero credentials.

Milestone 5 - Connected Realm Vertical Slice

Status: COMPLETED

Objective: Deliver typed Connected Realm cluster data by Connected Realm ID to serve as the structural foundation for the upcoming Auction House vertical slice.

Key Architectural Decisions Validated:

1. Canonical Connected Realm Cluster Lookup:
   - Direct lookup by numeric ID: $wow->connectedRealms()->get(id: 1127)
   - Strongly typed ConnectedRealm domain entity exposing int $id and list<Realm> $realms.
   - Reuses canonical UncannyWoW\Core\Domain\Model\Realm\Realm instances with 0 code duplication.

2. Contextual Fallback for Connected Realm ID in Realm Hydration:
   - Blizzard's /data/wow/connected-realm/{id} payload omits the connected_realm link on nested realm objects.
   - RealmHydrator::hydrate(array $data, ?int $fallbackConnectedRealmId = null): Realm accepts an optional fallback ID so member realms preserve their canonical connectedRealmId relation without modifying domain immutability.

3. Dynamic Namespace Reuse:
   - Queries /data/wow/connected-realm/{id} using BlizzardApiEndpointResolver::resolveDynamicNamespace($region) (e.g., dynamic-eu).

4. Strict Validation & Clean Exception Boundaries:
   - Enforces $id > 0 before executing network calls.
   - Yields ResourceNotFoundException(resourceType: 'connected-realm', identifier: '{region}:{id}') on 404.

5. PSR-6 Cache Key Strategy:
   - Cache key format: uw.cr.{region}.{hash48} (e.g., uw.cr.eu.abc... <= 64 chars).
   - Default TTL: 86400 seconds (24 hours).

6. Explicit Locale Contract at Repository Boundary:
   - Repository contract requires explicit non-null Locale parameter (ConnectedRealmRepositoryInterface::getById(Region $region, int $id, Locale $locale)).
   - Public ConnectedRealmService resolves client configuration defaults before invoking repository.

7. Deferred Capabilities (YAGNI/KISS):
   - Connected Realm Search and Connected Realm Index endpoints deferred (not required for direct ID-to-cluster resolution).
   - Volatile operational fields (status, population, has_queue) omitted to maintain cluster stability and high cacheability.

18. Milestone 6 Decisions (Auction House Vertical Slice)

1. Separation of Concerns:
   - Connected-Realm non-commodity auctions (`AuctionHouseSnapshot`) and Region-wide commodity auctions (`CommodityMarketSnapshot`) are completely distinct domain models and repository operations.
   - Raw auction listings are captured as snapshots without performing economy calculations, aggregations, min/max/average prices, or market value analysis.

2. Monetary Semantics & Exact Currency Types:
   - All price fields (`buyoutCopper`, `bidCopper`, `unitPriceCopper`) are strictly typed integers representing copper.
   - No floating-point math, no currency conversions, and no formatted string prices exist in the toolkit domain models.

3. Preservation of Item Identity & Variant Discriminators:
   - Non-commodity listings preserve equipment and pet variations via `AuctionItem` and `AuctionItemModifier` (`type`, `value`).
   - Discriminators (`context`, `bonus_lists`, `modifiers`, `pet_species_id`, `pet_breed_id`, `pet_level`, `pet_quality_id`) are preserved directly from Blizzard payloads.
   - Strictly ZERO Item API calls or N+1 lookups are performed during auction retrieval or hydration.

4. Closed Duration Categories:
   - `AuctionTimeLeft` enum models Blizzard's discrete duration categories (`SHORT`, `MEDIUM`, `LONG`, `VERY_LONG`). Unknown values trigger `InvalidResponseException`.

5. Exclusion of Locale from Auction Boundaries:
   - Blizzard auction payloads contain zero localized text strings.
   - Following prompt specifications, `Locale` is strictly excluded from `AuctionHouseService`, `AuctionHouseRepositoryInterface`, and cache identities.

6. Streaming JSON & Memory Scalability (Halaxa/JsonMachine):
   - Real-world regional commodity payloads (~50-80 MB raw JSON, hundreds of thousands of rows) exhaust PHP's default 256MB memory limit when loaded via full `json_decode()`.
   - `BlizzardApiClient::getStream()` yields a PSR-7 `StreamInterface` without loading the full body into memory.
   - `BlizzardAuctionHouseRepository` uses `halaxa/json-machine` (`Items::fromIterable()` with pointer `/auctions` and `ExtJsonDecoder(true)`) over 64KB stream chunks.
   - `AuctionHouseSnapshot` and `CommodityMarketSnapshot` implement `\IteratorAggregate`, yielding typed domain objects lazily through generators.
   - Live Validation Results (memory_limit: 256M):
     - Connected Realm 1127: 40,601 auctions fully iterated at ~6 MB peak memory.
     - EU commodities: 373,242 auctions fully iterated at ~6 MB peak memory.
     - In contrast, the original full `json_decode()` architecture crashed with `Allowed memory size of 268435456 bytes exhausted` on EU commodities.

7. Explicit Single-Pass Iteration Contract:
   - Snapshots are explicitly single-pass. Attempting a second `foreach` traversal throws `\LogicException('This auction snapshot has already been consumed.')`.
   - Why rewindability was rejected:
     - In-memory buffering (rewindable collections) would materialize hundreds of thousands of hydrated domain models in RAM, defeating the streaming memory guarantee.
     - Silent empty re-iteration would mask consumer logic bugs.
     - Re-fetching the Blizzard API on re-iteration would introduce unexpected side effects, rate-limit consumption, and race conditions against live auction market states.
   - Consumers requiring multi-pass analysis or indexing should ingest the stream into application storage (e.g. database, search index) during the single pass.

8. Cache Strategy & Decision:
   - In-memory snapshot caching via generic PSR-6 cache pools is inappropriate for multi-megabyte streaming generators (cannot cache generators directly without fully buffering rows).
   - `CachedAuctionHouseRepository` has been removed. Applications requiring persistent caching should store raw stream chunks or ingest rows into application storage (e.g., MySQL, Redis, ClickHouse).

9. Deferred Capabilities:
   - Economy analysis, price history, market trends, min/max/average calculations (deferred to Milestone 8 or consuming applications).

19. Milestone 7 Decisions (Economy Data Vertical Slice)

1. Separation of Concerns & Service Architecture:
   - `AuctionHouseService` retrieves raw, streamed, single-pass auction feeds.
   - `EconomyService` transforms and aggregates streaming data into compact, typed, provider-independent market summaries (`CommodityMarketData`, `ConnectedRealmMarketData`).
   - Pure domain aggregators (`CommodityMarketAggregator`, `ConnectedRealmMarketAggregator`) process streams on the fly without network or transport coupling.

2. Single-Pass Streaming Aggregation:
   - Aggregation consumes raw auctions on the fly without loading all listings into memory.
   - Raw `Auction` and `CommodityAuction` objects fall out of scope and are garbage collected immediately.
   - Memory scales with unique market identity cardinality and retained price depth, not raw auction count alone.
   - Validated Live Results (PHP `memory_limit` = 256M):
     - Connected Realm 1127: 41,076 raw auctions aggregated into 25,768 unique market variants (total quantity: 41,076), peaking at approximately 88 MB.
     - EU regional commodities: 381,693 raw auctions aggregated across 11,904 unique commodity item IDs (total quantity: 68,869,246), peaking at approximately 100 MB.
     - Both live feeds completed comfortably within the 256 MB PHP memory limit.

3. Semantic Market Identity:
   - Commodities are fungible and group strictly by `itemId`.
   - Non-commodities group by `MarketItemIdentity`, preserving item ID, context, bonus lists in received order, modifiers in received order, and all pet attributes (species, breed, level, quality).
   - Distinct variants are never collapsed into identical summaries.

4. Exact Integer Price Semantics & Bounded Depth:
   - Commodity price levels use native integer `priceCopper` (exact Blizzard unit price).
   - Non-commodity price levels use `NonCommodityPriceLevel`, preserving exact listing `buyoutCopper` and exact `quantityPerListing` (lot size) without lossy division.
   - Bid-only listings do not pollute buyout price levels.
   - Retains the lowest $N$ price levels in a single pass without sorting raw auctions: `maxPriceLevels` is hard-bounded to `1..10`, defaulting to `5`.
   - Naive weighted-average and outlier-sensitive statistics are strictly deferred: real-world EU commodity feeds exhibit extreme high-price listings (up to 499,999,700 copper observed in live validation samples), which would distort simple means and risk 32-bit/64-bit integer overflow without specialized statistical modeling.

5. Zero Secondary API Calls:
   - Strictly ZERO Item API calls are performed during economy aggregation. Summaries retain `itemId` or `AuctionItem`.

20. Milestone 8 Decisions (Opportunity Analysis Vertical Slice)

1. Pipeline & Architecture Separation:
   - Pipeline: Blizzard Auction House → M6 streaming → M7 Economy aggregation → M8 Opportunity Analysis.
   - Operates strictly on already-materialized M7 `CommodityMarketData` and `ConnectedRealmMarketData`.
   - Pure domain analyzers (`CommodityOpportunityAnalyzer`, `ConnectedRealmOpportunityAnalyzer`) perform in-memory computations without network calls, HTTP clients, or secondary API calls.
   - `OpportunityService` memoized on `UncannyWoWClient` (`$wow->opportunities()`).

2. Opportunity Definition & Terminology:
   - A structural opportunity candidate is an observed price spread within the current market snapshot between one or more low-priced acquisition levels and a higher observed target supply level.
   - Terminology is strictly prospective ("prospective gross revenue", "prospective net profit", "prospective ROI"): assumes successful resale at target price after Auction House fee deduction, without modeling deposit losses, liquidation time, or market velocity.

3. Exact Integer Arithmetic & Overflow Protection:
   - Currency is represented strictly in 64-bit integer copper. Floating-point arithmetic, BCMath, and GMP are prohibited.
   - `SafeIntegerMath` provides overflow-checked addition, subtraction, and multiplication.
   - Exact fee calculation uses `mulDivCeil` with a small bounded multiplier ($0 \le M \le 10000$), quotient/remainder decomposition, and binary double-and-add to eliminate intermediate integer overflow.
   - `Roi` preserves exact rational numerator/denominator (`profitCopper / acquisitionCostCopper`).
   - Floored integer basis points metric via `toBasisPoints()` using `mulDivFloor`.
   - Exact, loss-free ROI sorting via Euclidean continued fraction expansion in `Roi::compareTo()` without float rounding errors, basis-point truncation collisions, or multiplication overflow.

4. Multi-Quantity Barrier Rules (Non-Commodities):
   - Non-commodity price levels preserve exact total listing `buyoutCopper` and exact `quantityPerListing`.
   - Only listings with `quantityPerListing === 1` are eligible for acquisition or target pricing.
   - Any multi-quantity listing (`quantityPerListing > 1`) at or below a target price represents an unresolved lot barrier: it cannot be normalized, divided, or jumped across, and invalidates that target price.
   - Strict isolation within `MarketItemIdentity`: cross-variant price comparison is strictly forbidden.

5. Re-iterable, Lazy Analysis (`OpportunityAnalysis`):
   - Implements `Countable` and `\IteratorAggregate<int, T>` via a generator factory closure.
   - Iteration (`foreach`) and `filter()` operate with $O(1)$ additional memory.
   - `count()` runs in $O(1)$ memory and $O(U \times P)$ time.
   - Sorting (`sortByProfitDesc()`, `sortByRoiDesc()`, `sortByCapitalAsc()`) and non-overlapping strategy deduplication (`distinctByHighestProfit()`, `distinctByHighestRoi()`, `distinctByLowestCapital()`) explicitly materialize candidates into memory in $O(C)$ or $O(U)$ space.

6. Live Validation Results & Empirical Market Characteristics (PHP `memory_limit` = 256M):
   - Connected Realm 1127 (Non-Commodities):
     - Status: SUCCESS
     - Total structural candidate opportunities: 1,924 across all valid boundaries
     - Distinct variants with opportunities: 1,569
     - Acquisition capital range: 11,000 to 288,691,482,600 copper
     - Elapsed time: ~9.35 s
     - Peak memory: ~90 MB
   - EU Regional Commodities:
     - Status: SUCCESS
     - Total structural candidate opportunities: 20,955 across all valid boundaries
     - Distinct commodities with opportunities: 8,824
     - Acquisition capital range: 1,500 to 52,225,250,000 copper
     - Elapsed time: ~31.62 s
     - Peak memory: ~104 MB
   - Empirical Observations & Architectural Implications:
     - Live current-snapshot analysis exposes some extremely large structural spreads and extremely high ROI values caused by very high observed target listings.
     - This is expected behavior for Milestone 8:
       - structural opportunity != guaranteed sale
       - target listing != fair market value
       - high ROI != reliable opportunity
       - no historical demand or sales velocity exists yet in a single snapshot
     - Milestone 8 deliberately performs NO arbitrary outlier filtering, subjective scoring, or heuristic dampening: it accurately reports the raw mathematical spread between observed snapshot price levels. Outlier modeling, historical price trends, and demand scoring belong in future milestones or consuming applications.

21. Milestone 9A - Profession & Recipe Data Foundation

- Purpose:
  - Establish the authoritative, immutable game data foundation for professions, skill tiers, categories, and recipes required ahead of Crafting Profitability (Milestone 9B).
  - Strictly data foundation only: no probabilistic crafting models, proc economics, or profit analyzers.
- Core Endpoints & Namespaces:
  - Recipe Data: `/data/wow/recipe/{recipeId}` under `static-{region}` namespace.
  - Profession Data: `/data/wow/profession/{professionId}` under `static-{region}` namespace.
  - Skill Tier Data: `/data/wow/profession/{professionId}/skill-tier/{skillTierId}` under `static-{region}` namespace.
- Live Midnight Empirical Findings & Provider Limitations:
  - Discovered Live Tiers: Midnight Alchemy (Tier 2906, 56 recipes across 12 categories) and Midnight Blacksmithing (Tier 2907, 111 recipes across 11 categories).
  - Across all 167 scanned Midnight recipes, `crafted_quantity` is 100% absent (`0 / 167`).
  - Standard item-producing recipes (e.g. *Potion of Recklessness* 52686, *Primalforged Heavy Axe* 52349) also omit `crafted_item`. Instead, major crafting materials and customization options are exposed via `modified_crafting_slots`.
- Recipe Semantics:
  - `hasCraftedItemReference()`: Returns `true` only if Blizzard's Recipe payload explicitly supplied `crafted_item` with an item ID. Returning `false` strictly means the provider omitted the static link; it MUST NOT be interpreted as claiming the recipe produces no item in-game.
  - Direct Reagents (`$reagents`): Represents only the reagent list directly and statically exposed by Blizzard's Recipe payload. It is partial and incomplete for modern modified-crafting recipes.
  - Modified Crafting Slots (`$modifiedCraftingSlots`): Typed as `list<RecipeModifiedCraftingSlot>`, preserving Blizzard's `display_order`, `slotTypeId`, and localized `name`. Hydrated strictly without N+1 HTTP calls.
  - Yield / Quantity Tri-State: Omitted or null `crafted_quantity` hydrates to `RecipeCraftedQuantity::unknown()`. Fixed and range quantities remain fully supported for legacy/classic recipes. Malformed quantities throw `InvalidResponseException`.
- M9B Architectural Consequence:
  - The official Blizzard Recipe API is **NOT** sufficient to automatically construct a complete Midnight crafting economic plan.
  - Because modern Midnight recipes omit static `crafted_item` links, omit `crafted_quantity`, and place major reagents into `modified_crafting_slots`, the Recipe payload does not establish exact output item IDs, exact output yields, or complete material costs.
  - Future Milestone 9B must therefore rely on an explicit caller-supplied `CraftPlan` for these execution parameters. Recipe data provides authoritative metadata and context, but MUST NOT masquerade as a complete craft execution definition.
- Caching:
  - Standard PSR-6 caching in `CachedRecipeRepository` (prefix `uw.recipe.`) and `CachedProfessionRepository` (prefixes `uw.profession.` and `uw.skilltier.`) with 24h default TTL.

22. Decisions Summary

DECIDED NOW

Package: uncanny-wow/toolkit

Namespace: UncannyWoW\

PHP Version: PHP >= 8.3 (Tested against 8.3, 8.4, 8.5)

Standard Interfaces: PSR-18 Client, PSR-17 Request/Stream Factories, PSR-6 Cache Pool

Test Engine: PHPUnit

Formatter: PHP-CS-Fixer (.php-cs-fixer.dist.php)

Static Analysis: PHPStan Level 9

Scope: Character Profile, Realm Data, Item Data, Connected Realm, Auction House, Economy Data, Opportunity Analysis, and Profession & Recipe Data (Milestones 0 through 9A completed)

API Parameter Naming: Canonical $realmSlug for slug inputs in Character APIs; $slug in Realm APIs; $id for numeric Item, Recipe, Profession, and Connected Realm ID inputs; $connectedRealmId for Auction House, Economy, and Opportunity connected realm inputs; $professionId and $skillTierId for skill tier lookups

Service Memoization: Domain services memoized on UncannyWoWClient facade ($wow->characters(), $wow->realms(), $wow->items(), $wow->connectedRealms(), $wow->auctionHouse(), $wow->economy(), $wow->opportunities(), $wow->recipes(), $wow->professions())

Namespace Support: Profile (profile-{region}), Dynamic (dynamic-{region}), and Static (static-{region}) namespaces

DEFERRED

Symfony Bridge (UncannyWoW\Bridge\Symfony)

Secondary Data Providers

Additional WoW Domain APIs (Guilds, Mythic+, Raids)

Multi-period historical trend analysis & price forecasting

Connected Realm Search & Index Endpoints (direct ID lookup satisfies Auction House foundation)

Realm Index Endpoint (Search API satisfies discovery)

Item Media Endpoint (avoiding redundant secondary HTTP requests during item lookup)

Item Search Endpoint (Item ID to metadata resolution satisfies requirements)

ClientConfiguration Splitting (deferred until additional domain needs emerge)

Monorepo Sub-Package Splitting

22. Final Recommendation

This architecture freezes the foundational decisions through Milestone 8. It establishes credential security rules, isolates OAuth endpoint resolution, enforces pragmatic domain modeling, memory-safe streaming, exact integer arithmetic, and incorporates the findings of the Milestone 2 Architecture Review, Milestone 3 Realm vertical slice, Milestone 4 Item Data vertical slice, Milestone 5 Connected Realm vertical slice, Milestone 6 Auction House vertical slice, Milestone 7 Economy Data vertical slice, and Milestone 8 Opportunity Analysis vertical slice.
