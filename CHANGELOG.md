# Changelog

All notable changes to `nombaone/nombaone-php` are documented here.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [0.1.2] - 2026-07-07

### Changed

- When no PSR-17/PSR-18 HTTP implementation can be auto-discovered, the client
  now throws a clear `NombaOneException` telling you to install one (e.g.
  `composer require guzzlehttp/guzzle`) or pass your own, instead of surfacing
  php-http/discovery's cryptic `DiscoveryFailedException`.

## [0.1.1] - 2026-07-07

### Changed

- Release automation: a green `main` now auto-tags `vX.Y.Z` from the version
  line in `src/Version.php` (Packagist ingests the tag), so bumping that line and
  merging is the entire release ritual. Documented in `PUBLISHING.md`.

## [0.1.0] - 2026-07-05

Initial release — the official PHP SDK for the NombaOne subscription-billing API.

### Added

- **Client** `NombaOne\Nombaone` with host derivation from the key prefix
  (`nbo_sandbox_…` / `nbo_live_…`), `NOMBAONE_API_KEY` support, and configurable
  `baseUrl`, `timeout`, `maxRetries`, `defaultHeaders`, and HTTP transport.
- **Transport** built on a pluggable `HttpClient` seam (default: a curl client
  honoring per-attempt timeouts; a `Psr18HttpClient` adapter for any PSR-18
  client, both discovered via `php-http/discovery`). Automatic retries with
  full-jitter backoff, `Retry-After` support, and an `Idempotency-Key` computed
  once per call and reused across retries so a POST can never double-charge.
- **Typed exceptions** (`ApiException` + `BadRequest`/`Authentication`/
  `PermissionDenied`/`NotFound`/`Conflict`/`Validation`/`RateLimit`/`Server`,
  plus `Connection`/`Timeout`/`WebhookVerification`), each carrying `errorCode`,
  `hint` (folded into the message), `docUrl`, `fields`, and `requestId`. The 72
  public error codes are vendored as `NombaOne\ErrorCode` constants.
- **Cursor pagination** via `NombaOne\Page` — `->data`, `->hasNextPage()`,
  `->nextPage()`, and `foreach` auto-iteration across pages (a `Generator`).
- **The full resource surface**, arrays in / typed readonly DTOs out:
  `customers`, `plans` (+ nested `prices`), `prices`, `subscriptions`
  (+ `schedule`, `dunning`), `invoices`, `coupons`, `paymentMethods`, `mandates`,
  `settlements`, `webhookEndpoints` (+ `deliveries`), `events`, `organization`
  (+ `billing`), `metrics`, and `sandbox`. Every returned object carries the
  originating response via `getLastResponse()`.
- **Webhooks helper** `NombaOne\Webhooks\Webhooks` — `constructEvent()`,
  `verifySignature()`, and `generateTestHeader()`, usable without an API key.
  HMAC-SHA256 over `"{t}.{rawBody}"`, constant-time comparison, configurable
  timestamp tolerance, and multi-signature (secret rotation) support. Typed,
  open event-type catalog in `NombaOne\Webhooks\WebhookEventType`.
- **Test suite**: unit + conformance (bidirectional coverage against a committed
  `spec/openapi.json`) run in CI across PHP 8.2/8.3/8.4; an env-gated integration
  suite against a real API; five runnable examples.
- **Full-surface live verifier** (`composer verify`) exercising all 78 methods
  against the deployed sandbox with a per-method result and a scannable verdict
  (`78 methods | ok … | DEFECTS 0`), including wire-`domain` checks that catch a
  method returning the wrong object type.

### Fixed

- `subscriptions->updatePaymentMethod()` now returns a `PaymentMethod` (the wire
  returns the attached payment method, not the subscription — the OpenAPI spec is
  wrong here). Caught by the full-surface verifier before release.

[Unreleased]: https://github.com/nombaone/nombaone-php/compare/v0.1.2...HEAD
[0.1.2]: https://github.com/nombaone/nombaone-php/compare/v0.1.1...v0.1.2
[0.1.1]: https://github.com/nombaone/nombaone-php/compare/v0.1.0...v0.1.1
[0.1.0]: https://github.com/nombaone/nombaone-php/releases/tag/v0.1.0
