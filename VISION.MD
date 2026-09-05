Uncanny-WoW Toolkit - Vision

Purpose

Uncanny-WoW Toolkit exists to provide a clean, reusable PHP foundation for applications that consume and process World of Warcraft data.

The goal is simple:

Developers should work with World of Warcraft concepts, not low-level integration details.

Instead of repeatedly handling OAuth flows, HTTP requests, endpoint structures, response normalization, caching, and provider-specific quirks, the toolkit should expose clear PHP APIs built around useful domain concepts such as characters, guilds, items, encounters, raids, dungeons, Mythic+, and economy data.

Why this project exists

World of Warcraft data is available through several APIs and external providers, but applications often end up rebuilding the same technical foundations:

authentication

HTTP clients

request construction

locale and namespace handling

response validation

caching

retries and error handling

data normalization

mapping raw responses to usable application objects

synchronization logic

Uncanny-WoW Toolkit aims to centralize these recurring concerns into a reliable and reusable library.

This allows applications built on top of it to focus on their own features instead of rewriting integration code.

Long-term role

The toolkit is intended to become a shared technical foundation for the Uncanny-WoW ecosystem.

It may eventually support applications such as:

the Uncanny-WoW website

character and guild tools

raid and encounter databases

Mythic+ tools

auction house and economy dashboards

the Road to 7 Million Gold project

CLI utilities

Discord bots

data synchronization services

future WoW-related applications

The toolkit itself should remain generic.

Business logic that belongs to a specific website, video project, dashboard, or application should remain outside the toolkit.

Core principles

1. Domain-first API

The public API should reflect World of Warcraft concepts rather than raw HTTP endpoints.

A developer should think in terms of:

characters

guilds

realms

items

raids

encounters

dungeons

Mythic+

auctions

market data

The underlying provider implementation should remain an internal concern whenever possible.

2. Strong typing

The project should use strict PHP typing throughout the codebase.

Public methods should expose predictable types and domain objects instead of loosely structured arrays whenever practical.

The objective is to make incorrect usage easier to detect during development.

3. Reliability before speed of delivery

The toolkit is intended to become infrastructure used by several applications.

For that reason, quality takes priority over rushing features.

New functionality should aim to include:

clear interfaces

validation

predictable error handling

automated tests

documentation

backward-compatible design where reasonable

A feature is not considered complete only because it works once.

4. Provider abstraction

Blizzard will be the first major integration, but the architecture should not assume that all useful data comes from one source.

The toolkit should be able to integrate additional providers when they offer useful complementary data.

Examples may include:

Blizzard APIs

auction house data providers

historical market-data services

ranking and progression services

other WoW-related data sources

Provider-specific behavior should remain isolated behind clear boundaries.

5. Cache by design

External API calls are limited, slower than local operations, and often return data that does not need to be refreshed continuously.

Caching should therefore be considered part of the architecture, not an afterthought.

The toolkit should make it easy to:

cache responses

define sensible lifetimes

avoid duplicate requests

refresh stale data

support application-level synchronization strategies

6. Framework-friendly, not framework-dependent

Symfony will be a first-class integration target because it is part of the Uncanny-WoW ecosystem.

However, the core library should remain usable in a regular PHP application whenever possible.

Symfony-specific conveniences should sit around the core rather than defining it.

7. Clear separation of responsibilities

The toolkit should separate:

transport

authentication

provider integrations

normalization

domain models

caching

application-specific business logic

This separation is intended to make the project easier to test, maintain, extend, and reuse.

Initial scope

The first development phase will focus on building a strong Blizzard API foundation.

The first milestones should cover:

project structure

configuration

Blizzard OAuth authentication

HTTP communication

error handling

caching foundations

first typed domain responses

automated tests

documentation and usage examples

Once this foundation is stable, the toolkit can expand progressively.

Planned capabilities

Potential future areas include:

Blizzard integration

authentication

realms

characters

guilds

items

professions

achievements

playable classes and specializations

connected realms

PvE data

raids

encounters

bosses

dungeons

Mythic+

seasons

progression-related data

Economy

auction house data

commodities

item pricing

price history

market trends

liquidity indicators

profitability helpers

external market-data integrations

Developer tooling

caching helpers

synchronization helpers

CLI commands

diagnostics

logging hooks

fixtures and testing utilities

These capabilities are directional rather than a fixed promise.

They should be implemented according to actual product needs and technical maturity.

Quality expectations

The project should aim for a high engineering standard from the beginning.

Expected practices include:

PHP strict types

PSR-compatible code

automated testing

static analysis

code style automation

continuous integration

clear versioning

documented breaking changes

meaningful commit history

small, reviewable changes

The exact tooling will be selected as the project foundation is implemented.

Project maturity

Uncanny-WoW Toolkit is currently in its foundation phase.

The immediate objective is not to make the project public for external contribution as quickly as possible.

The priority is to:

validate the architecture

prove the main integrations

test the toolkit against real Uncanny-WoW use cases

identify weak design decisions early

stabilize the public API

establish quality standards

The project may be opened more broadly later, once the foundation is considered sufficiently stable and useful.

Until then, development decisions should still be made with long-term maintainability and future external use in mind.

Success criteria

The toolkit will be successful if it allows a developer to build a new World of Warcraft feature without having to rediscover how each underlying API works.

A good developer experience should eventually look like this:

$character = $wow->characters()->profile(
    realm: 'la-croisade-ecarlate',
    name: 'norigosa',
);

The developer should not need to care about OAuth token renewal, endpoint URLs, response decoding, or provider-specific plumbing unless they explicitly want to.

That is the standard Uncanny-WoW Toolkit should aim for.

Guiding rule

When deciding whether a feature belongs in the toolkit, ask:

Is this functionality generic and reusable across multiple World of Warcraft applications?

If the answer is yes, it may belong in the toolkit.

If it only serves one specific application, dashboard, website, or content project, it should remain outside the toolkit.
