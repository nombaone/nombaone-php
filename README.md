# nombaone/nombaone-php

The official PHP SDK for the [Nomba One](https://nombaone.xyz) subscription-billing API — recurring billing for Nigeria over card, direct debit, bank transfer, and more, with dunning that recovers and a ledger that never loses a kobo.

```bash
composer require nombaone/nombaone-php
```

Requires PHP 8.2+ and any [PSR-18](https://www.php-fig.org/psr/psr-18/) HTTP client + [PSR-17](https://www.php-fig.org/psr/psr-17/) factories, which the SDK auto-discovers ([Guzzle](https://docs.guzzlephp.org/), Symfony HttpClient, Nyholm, …). Server-side only — there is no publishable key.

## Quickstart

Grab a sandbox key (`nbo_sandbox_…`) from the [dashboard](https://console.nombaone.xyz), set it as `NOMBAONE_API_KEY`, and you are three objects away from a live subscription:

```php
use NombaOne\Nombaone;

$nomba = new Nombaone(getenv('NOMBAONE_API_KEY'));

$plan = $nomba->plans->create(['name' => 'Pro']);
$price = $nomba->plans->prices->create($plan->id, [
    'unitAmountInKobo' => 250_000, // ₦2,500.00 per month
    'interval'         => 'month',
]);
$customer = $nomba->customers->create([
    'email' => 'ada@example.com',
    'name'  => 'Ada Lovelace',
]);

// Sandbox: mint a deterministic test card, then subscribe.
$method = $nomba->sandbox->createPaymentMethod(['customerId' => $customer->id]);
$subscription = $nomba->subscriptions->create([
    'customerId'      => $customer->id,
    'priceId'         => $price->id,
    'paymentMethodId' => $method->id,
]);

echo $subscription->status; // "active"
```

The client derives the host from your key prefix — `nbo_sandbox_…` talks to `https://sandbox.api.nombaone.xyz`, `nbo_live_…` to `https://api.nombaone.xyz`.

You pass **associative arrays** in and receive **typed, readonly objects** out (`$subscription->status`, `$customer->email`) — with your IDE autocompleting every field.

## Sandbox first

The sandbox runs the real billing engine. `$nomba->sandbox->*` gives you the levers to make a month happen in a second:

```php
// A card that declines like a thin balance does — "not yet", not "no".
$nomba->sandbox->createPaymentMethod([
    'customerId' => $customer->id,
    'behavior'   => 'decline_insufficient_funds', // or success | requires_otp | decline_expired_card | decline_do_not_honor
]);

// The test clock: force the next billing cycle through the real engine.
$cycle = $nomba->sandbox->advanceCycle($subscription->id);
echo $cycle->outcome; // "paid" | "past_due" | …

// Fire a real, signed webhook at your registered endpoints.
$nomba->sandbox->simulateWebhook(['type' => 'invoice.payment_failed']);
```

These methods throw locally (before any network call) if used with a live key.

## Money is integer kobo

Every amount in the API is an **integer in kobo**: `₦1.00 = 100`. `250_000` is ₦2,500 — not ₦250,000. No floats, no decimal strings, `currency` is always `"NGN"`. Multiply naira by 100 exactly once, at the edge of your system; every money field is suffixed `InKobo` so a mixup is hard to type.

## Pagination

Every `list()` works three ways:

```php
// One page.
$page = $nomba->invoices->list(['status' => 'open', 'limit' => 50]);
$page->data;                    // list<Invoice>
$page->pagination->hasMore;
$page->pagination->nextCursor;

// Manual paging.
if ($page->hasNextPage()) {
    $next = $page->nextPage();  // same filters, next cursor
}

// Or let the SDK thread the cursors — foreach walks every page.
foreach ($nomba->invoices->list(['status' => 'open']) as $invoice) {
    // every item across every page; pages are fetched as you consume them
}
```

## Errors are a feature

Failures throw typed exceptions carrying everything the API said — the stable `errorCode` to branch on, a `hint` telling you exactly what to do next (folded into the message), a `docUrl` into the error reference, per-field details on validation failures, and the `requestId` to quote to support:

```php
use NombaOne\ErrorCode;
use NombaOne\Exceptions\NotFoundException;
use NombaOne\Exceptions\RateLimitException;
use NombaOne\Exceptions\ValidationException;

try {
    $nomba->subscriptions->create(['customerId' => $id, 'priceId' => $priceId]);
} catch (ValidationException $e) {
    print_r($e->fields);              // ['paymentMethodId' => ['Required']]
} catch (RateLimitException $e) {
    echo $e->retryAfter;              // seconds
} catch (NotFoundException $e) {
    if ($e->errorCode === ErrorCode::CUSTOMER_NOT_FOUND) { /* … */ }
}
```

> PHP's `\Exception` reserves `$code` for its own numeric code, so the machine-readable error code is on `$e->errorCode`. Compare it against the `NombaOne\ErrorCode` constants.

| Status | Exception                    | Notes                                         |
| ------ | ---------------------------- | --------------------------------------------- |
| 400    | `BadRequestException`        | malformed request                             |
| 401    | `AuthenticationException`    | missing / invalid / wrong-environment key     |
| 403    | `PermissionDeniedException`  | missing scope, foreign resource               |
| 404    | `NotFoundException`          | wrong id or wrong environment                 |
| 409    | `ConflictException`          | state conflicts, idempotency reuse            |
| 422    | `ValidationException`        | `$e->fields` has the per-field messages       |
| 429    | `RateLimitException`         | `$e->retryAfter`, `$e->limit`, `$e->remaining`|
| 5xx    | `ServerException`            | safe to retry (the SDK already did)           |
| —      | `ConnectionException` / `TimeoutException` | transport-level                  |

All of these extend `NombaOne\Exceptions\ApiException` (except the transport ones), which extends `NombaOne\Exceptions\NombaOneException` — catch the base to handle any SDK error.

## Idempotency & retries

The SDK auto-generates an `Idempotency-Key` for every POST and **reuses it across its automatic retries** (network failures, timeouts, 408/429/5xx, and its own in-flight idempotency conflict — 2 retries by default, honoring `Retry-After`), so a blip can never double-charge. Pass your own key when the operation must stay idempotent across _process_ restarts:

```php
$nomba->settlements->createPayout(
    ['amountInKobo' => 5_000_000, 'bankCode' => '058', 'accountNumber' => '0123456789'],
    ['idempotencyKey' => "payout-{$myPayoutRow->id}"], // ⚠ doubles as the payout's durable merchantTxRef
);
```

> **Payouts:** the `Idempotency-Key` doubles as the payout's durable `merchantTxRef`. Always pass an explicit, stable key (e.g. your own payout id) — an auto-generated key protects SDK-level retries, but a brand-new process retrying with a fresh key would create a **second payout**.

Every method also accepts a per-call options array as its final argument: `idempotencyKey`, `headers`, `timeout` (seconds), `maxRetries`.

## Webhooks

Verify before you parse, and dedupe on the event id — delivery is at-least-once, never exactly-once. The helper needs only the signing secret, **never an API key**:

```php
use NombaOne\Webhooks\Webhooks;
use NombaOne\Webhooks\WebhookEventType;

$webhooks = new Webhooks(); // or $nomba->webhooks

$event = $webhooks->constructEvent(
    $rawBody,                                  // the RAW request body — never re-serialize
    $signatureHeader,                          // the `X-Nombaone-Signature` header
    getenv('NOMBAONE_WEBHOOK_SECRET'),         // shown once when you created the endpoint
);

if ($store->alreadyProcessed($event->dedupeKey())) {
    http_response_code(200);                   // at-least-once ⇒ dedupe on the event id
    return;
}

match ($event->type) {
    WebhookEventType::INVOICE_PAID            => unlock($event->data['reference']),
    WebhookEventType::INVOICE_ACTION_REQUIRED => notify($event->data['checkoutLink']),
    WebhookEventType::INVOICE_PAYMENT_FAILED  => flag($event->data['reason']),
    default                                   => null,
};

http_response_code(200); // respond 2xx fast; do heavy work async
```

`constructEvent` checks the `X-Nombaone-Signature` (`t=<unix>,v1=<hex>`, HMAC-SHA256 over `` "{t}.{rawBody}" ``) in constant time, rejects stale timestamps (300s tolerance, configurable), and accepts multiple `v1=` pairs so secret rotation never drops a delivery. `generateTestHeader()` lets you unit-test your handler.

**Capture the raw body before your framework parses it:**

| Framework   | Raw body                             |
| ----------- | ------------------------------------ |
| Laravel     | `$request->getContent()`             |
| Symfony     | `$request->getContent()`             |
| Slim / PSR-7| `(string) $request->getBody()`       |
| Plain PHP   | `file_get_contents('php://input')`   |

Manage endpoints via `$nomba->webhookEndpoints` (create/rotate return the secret **exactly once**).

## The full surface

`customers` (+credit, discount) · `plans` (+nested `prices`) · `prices` · `subscriptions` (pause/resume/cancel/resubscribe/change, `schedule`, `dunning`, upcoming invoice, events) · `invoices` · `coupons` · `paymentMethods` (hosted-checkout cards, virtual accounts) · `mandates` (NIBSS direct debit) · `settlements` (escrow, refunds, payouts) · `webhookEndpoints` (+deliveries, replay) · `events` (+catalog) · `organization` (+billing policy) · `metrics` · `sandbox` — every operation in the [API reference](https://docs.nombaone.xyz), 1:1.

Worth knowing:

- **Mandates are asynchronous.** They start `consent_pending` and activate when the customer's bank confirms — listen for `payment_method.updated`, don't poll, don't charge early.
- **Bank transfer is a push rail.** `paymentMethods->createVirtualAccount()` issues a NUBAN; collection completes when the transfer arrives and reconciles.
- **`past_due` is not canceled.** Read `subscriptions->dunning->retrieve()` and honor `graceAccessUntil` before cutting anyone off. Involuntary churn is `status: canceled` with `cancellationReason: involuntary`.
- **Some request filters use `…Ref`, not `…Id`.** Prices filter by `planRef`; payment-methods and mandates take `customerRef`. The SDK mirrors these wire names faithfully.

## Configuration

```php
new Nombaone($apiKey, [
    'baseUrl'    => 'https://sandbox.api.nombaone.xyz', // override the derived host
    'timeout'    => 30.0,   // per-attempt seconds
    'maxRetries' => 2,      // automatic retry budget
    'httpClient' => $client, // a NombaOne\Http\HttpClient, or any PSR-18 client
    'defaultHeaders' => ['X-My-Tag' => 'checkout'],
]);
```

`$apiKey` defaults to `NOMBAONE_API_KEY`. You can also pass a single options array: `new Nombaone(['apiKey' => …, 'timeout' => 10])`. Read-only `$nomba->mode` and `$nomba->baseUrl` reflect the resolved environment.

## Examples & development

Runnable scripts live in [`examples/`](examples) — quickstart, pagination, the subscription lifecycle, a webhook receiver, and a dunning rehearsal with the test clock. To develop the SDK itself:

```bash
composer install
composer cs:check       # coding standard (PHP-CS-Fixer, PSR-12)
composer phpstan        # static analysis (PHPStan, max level)
composer test           # unit + conformance tests
NOMBAONE_API_KEY=nbo_sandbox_… composer test:integration   # live suite
```

## Requirements & versioning

PHP ≥ 8.2 (tested on 8.2, 8.3, 8.4). Semantic versioning; the API itself is versioned at `/v1` and additive changes never break you. MIT licensed. Copyright © Nomba One.
