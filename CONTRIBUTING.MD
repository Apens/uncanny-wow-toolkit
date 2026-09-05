Contributing to Uncanny-WoW Toolkit

Thank you for your interest in contributing to Uncanny-WoW Toolkit.

The project is currently in its foundation phase. External contributions may be limited until the architecture and public API are considered stable, but the contribution rules are defined early to keep the codebase consistent from the beginning.

Development principles

Contributions should follow the main principles defined in VISION.md:

reliability before speed

strict typing

clear separation of responsibilities

reusable, generic functionality

predictable APIs

automated tests

documented behavior

framework-friendly core design

If a feature is specific to only one application or one content project, it probably does not belong in the toolkit.

Before contributing

Before starting a significant change:

Check whether the feature belongs in the toolkit.

Keep the scope as small and focused as possible.

Avoid introducing provider-specific behavior into generic layers.

Prefer extending existing abstractions instead of duplicating logic.

Consider backward compatibility before changing a public API.

For larger architectural changes, the expected approach is to discuss the design before implementation.

Branching

Development should happen on a dedicated branch.

Recommended naming conventions:

feature/blizzard-authentication
feature/character-profile
fix/token-refresh
refactor/http-client
docs/update-vision
test/character-client

Keep branches focused on a single concern whenever possible.

Commit messages

Use clear and concise commit messages.

Preferred format:

type: short description

Examples:

feat: add Blizzard OAuth client
fix: handle expired access tokens
test: add character profile tests
docs: document cache strategy
refactor: extract HTTP transport interface
chore: configure static analysis

Common commit types:

feat - new functionality

fix - bug fix

test - tests

docs - documentation

refactor - code restructuring without behavior change

chore - tooling, maintenance, configuration

perf - performance improvement

ci - continuous integration changes

Coding standards

The project should follow modern PHP practices.

Expected standards include:

strict types enabled

typed properties

typed parameters and return values

explicit visibility

small and focused classes

dependency injection where appropriate

interfaces at meaningful architectural boundaries

meaningful names

minimal hidden side effects

no unnecessary framework coupling in the core

PSR-compatible structure and conventions

Avoid returning loosely structured arrays from public APIs when a dedicated value object or domain object would provide a clearer contract.

Testing

New functionality should include automated tests whenever practical.

Tests should cover:

expected behavior

important edge cases

invalid input

external provider failures

authentication failures

unexpected or incomplete API responses

regression cases for bug fixes

A bug fix should ideally include a test that fails before the fix and passes after it.

Tests should not depend unnecessarily on live external APIs.

Where possible, external integrations should be isolated behind interfaces so they can be tested using fixtures, fakes, or mocked transports.

Error handling

Errors should be explicit and predictable.

Do not silently swallow failures.

Prefer meaningful exceptions over generic runtime errors when the caller may need to react differently.

Provider-specific errors should be translated into toolkit-level exceptions where doing so improves the developer experience.

Documentation

Any public-facing feature should include enough documentation for another developer to understand how to use it.

Documentation may include:

PHPDoc where useful

README examples

dedicated documentation files

usage examples

notes about provider limitations

upgrade notes for breaking changes

Code should be understandable without requiring knowledge of the underlying Blizzard endpoint structure.

Backward compatibility

Once public APIs begin to stabilize, avoid breaking changes unless they are necessary.

Breaking changes should be:

intentional

documented

justified

grouped into appropriate releases

Do not casually rename public methods, classes, constructor arguments, or return types.

Pull requests

A pull request should be small enough to review comfortably.

It should explain:

what changed

why the change is needed

how it was tested

whether it changes public behavior

whether it introduces a breaking change

Avoid mixing unrelated refactors, formatting changes, and new features in the same pull request.

Definition of Done

A change is considered complete when the relevant items below are satisfied:

The feature solves a real, defined problem.

The implementation fits the architecture described in VISION.md.

The code uses strict and predictable types.

Responsibilities are clearly separated.

Public APIs are understandable and consistent.

Relevant automated tests exist and pass.

Important edge cases are covered.

Error handling is explicit.

Documentation has been updated where necessary.

No unnecessary application-specific logic has entered the toolkit.

Static analysis passes once configured.

Code style checks pass once configured.

Continuous integration passes once configured.

The change does not introduce an undocumented breaking change.

The commit history is understandable.

Not every checkbox will apply to every documentation or maintenance change, but production code should meet the relevant quality requirements before being considered complete.

Security

Do not commit secrets.

This includes:

Blizzard client IDs

Blizzard client secrets

API keys

access tokens

refresh tokens

private credentials

production configuration

Secrets must be supplied through environment variables or another appropriate secure configuration mechanism.

If a credential is committed accidentally, treat it as compromised and rotate it immediately.

Project maturity

Uncanny-WoW Toolkit is still evolving.

Some conventions may change while the foundation is being established. The goal is not to freeze the project too early, but to make every architectural decision deliberate and documented.

The standard remains simple:

Build the toolkit as if other applications will depend on it for years.
