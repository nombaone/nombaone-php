# nombaone/nombaone-php — build summary

Official PHP SDK for the NombaOne subscription-billing API. Built end-to-end
against `SDK-WORKFLOW.md`, phase-gated, and verified against the deployed
sandbox (`https://sandbox.api.nombaone.xyz`).

## Status: complete (v0.1.0)

- **Full §4 surface**, 78 methods across 15 namespaces — conformance suite proves
  bidirectional coverage against a committed `spec/openapi.json`, and the
  deliberate-break drill was performed (broke a path → red naming the route →
  reverted).
- **153 unit + conformance tests / 377 assertions** green; PHPStan max clean;
  PHP-CS-Fixer (PSR-12) clean.
- **Same-idempotency-key-across-retries** unit test passes (the money-safety invariant).
- **Webhook golden vector passes byte-for-byte** (`ba56a072…`) plus the full
  rejection matrix (tamper, wrong secret, stale/future timestamp, multi-`v1`
  rotation, missing/malformed header, missing secret, non-JSON body, flat-shape).
- **Integration suite green against the live sandbox** (5 tests): full lifecycle to
  an active subscription, `advanceCycle` → paid invoice, upcoming-invoice + dunning
  reads, real-cursor pagination, idempotency replay returning the identical
  resource, typed-error assertions, and a webhook-endpoint lifecycle.
- **Full-surface live verifier** (`composer verify`, `bin/full-surface-verify.php`)
  exercised **every** method against the deployed sandbox:
  **`78 methods | ok 69 | expected-errors 8 | infra 1 | DEFECTS 0`**. It checks
  each returned object's wire `domain` against what the method's signature
  promises — which **caught a real bug**: `subscriptions->updatePaymentMethod()`
  returns a `PaymentMethod`, not a `Subscription` (the same mismatch the release
  playbook warns about). Fixed, re-verified DEFECTS 0. The 1 infra is the known
  sandbox mandate-create `504`; the 8 expected-errors are typed API errors on
  methods needing state not set up (no settlement subaccount, voiding a paid
  invoice, replaying a nonexistent delivery, etc.).
- **All 5 examples executed for real** with output shown.
- **Package consumed from a clean scratch Composer project** (mirrored dist +
  auto-discovered Guzzle) with one real API call.

## New quirk discovered (report to operator)

**Empty request bodies must be serialized as `{}`, not `[]`.** PHP's
`json_encode([])` produces the JSON array `[]`, which the API's body validators
reject — every no-body POST (`cancel`, `pause`, `resume`, `archive`,
`deactivate`, `setDefault`, `rotateSecret`, delivery `replay`, `sandbox
advanceCycle`, and `refund`/`void`/`create`/`update` when called with no fields)
returns **422 `CLIENT_VALIDATION_FAILED`**. Unit tests could not catch this
(PHP's `json_decode('{}')` and `json_decode('[]')` both yield `[]`); it only
surfaced when `subscriptions->cancel()` failed against the live sandbox. Fixed in
the transport by forcing an empty top-level body to `{}`.

This is a language-serialization trap, not a backend bug — but it will bite **any
SDK in a language where the empty map/array distinction is ambiguous** (PHP,
and to a degree Ruby/older serializers). Worth a note in the shared SDK-WORKFLOW
traps list (§10): *"no-body POSTs must send `{}`; guard against your language
emitting `[]` for an empty map."*

## Known backend quirks confirmed live

1. `mode` is `sandbox`/`live` on the wire (spec says `test`/`live`) — typed as strings.
2. Error `docUrl` is served from `docs.nombaone.com` — passed through verbatim
   (observed: `https://docs.nombaone.com/errors#CUSTOMER_NOT_FOUND`).
3. Creates return **201**; the SDK treats any 2xx as success.
4. Idempotency replay (same key + same body) returns the original resource id —
   verified live.
5. `cancel(mode: at_period_end)` leaves `status: active` with
   `cancelAtPeriodEnd: true` (not a quirk — documented as expected).

## Reconciliations against the Node reference

Vendored from the **API source**, not the Node SDK, where they had drifted:

- `API_KEY_HOST_MISMATCH` and `QUOTA_EXCEEDED` are public error codes the Node
  SDK's list omitted/mislabeled — the PHP `ErrorCode` catalog carries the exact 72.
- `Price`, `Invoice`, and `Coupon` response objects carry more fields than the
  Node interfaces modeled (e.g. `usageType`, `billingScheme`, `intervalCount`,
  `trialPeriodDays`, `active`; the full invoice amount breakdown) — modeled in full.

## Not done (needs operator access)

- Packagist publish and the GitHub remote/CI run (needs org credentials). The
  package builds, validates, and is Composer-installable; CI config is committed.
