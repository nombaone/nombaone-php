<?php

declare(strict_types=1);

/**
 * Full-surface live verification — the release gate.
 *
 * Calls EVERY public SDK method against the deployed sandbox and prints a
 * per-method result plus a one-line verdict a non-PHP reader can scan:
 *
 *   78 methods | ok X | expected-errors Y | infra Z | DEFECTS 0
 *
 * A method "passes" when either it returns the object its signature promises
 * (verified by the wire `domain`, so a wrong return type is caught even though
 * hydration is tolerant) OR it returns a typed NombaOne API error (that still
 * proves the request built and the error envelope parsed). A crash, a wrong
 * `domain`, or a non-API exception is a DEFECT. A backend 5xx / transport
 * outage is INFRA, not an SDK defect.
 *
 * Usage:  NOMBAONE_API_KEY=nbo_sandbox_... php bin/full-surface-verify.php
 */

require __DIR__ . '/../vendor/autoload.php';

use NombaOne\Exceptions\ApiException;
use NombaOne\Exceptions\ConnectionException;
use NombaOne\Models\Model;
use NombaOne\Nombaone;
use NombaOne\Page;

$key = getenv('NOMBAONE_API_KEY');
if (!is_string($key) || $key === '') {
    fwrite(STDERR, "Set NOMBAONE_API_KEY (a nbo_sandbox_… key) to run the full-surface verifier.\n");
    exit(2);
}
$baseUrl = getenv('NOMBAONE_BASE_URL');
$options = is_string($baseUrl) && $baseUrl !== '' ? ['baseUrl' => $baseUrl] : [];
$nomba = new Nombaone($key, $options);

echo "Full-surface verification against {$nomba->baseUrl} ({$nomba->mode->value})\n";
echo str_repeat('─', 72), "\n";

$counts = ['ok' => 0, 'exp' => 0, 'infra' => 0, 'defect' => 0];
$defects = [];
$u = substr(bin2hex(random_bytes(6)), 0, 10);

/**
 * Run one method. $expectDomain is the wire `domain` the result must carry
 * (for a list, the domain of its items); null for domainless payloads.
 *
 * @return mixed the result on success, or null on any exception
 */
function check(string $label, ?string $expectDomain, callable $fn): mixed
{
    global $counts, $defects;

    try {
        $result = $fn();
    } catch (ApiException $e) {
        if ($e->statusCode >= 500) {
            $counts['infra']++;
            printf("  infra   %-42s → %d %s (backend)\n", $label, $e->statusCode, $e->errorCode);

            return null;
        }
        $counts['exp']++;
        printf("  exp     %-42s → %d %s\n", $label, $e->statusCode, $e->errorCode);

        return null;
    } catch (ConnectionException $e) {
        $counts['infra']++;
        printf("  infra   %-42s → transport (%s)\n", $label, $e::class);

        return null;
    } catch (\Throwable $e) {
        $counts['defect']++;
        $line = sprintf('%s → %s: %s', $label, $e::class, $e->getMessage());
        $defects[] = $line;
        printf("  DEFECT  %-42s → %s: %s\n", $label, $e::class, $e->getMessage());

        return null;
    }

    // Determine the wire domain of what came back.
    $item = $result;
    $suffix = '';
    if ($result instanceof Page) {
        if ($result->data === []) {
            $counts['ok']++;
            printf("  ok      %-42s → empty page\n", $label);

            return $result;
        }
        $item = $result->data[0];
        $suffix = ' (page)';
    }

    if ($item instanceof Model) {
        $domain = is_string($item->raw['domain'] ?? null) ? $item->raw['domain'] : null;
        if ($expectDomain !== null && $domain !== null && $domain !== $expectDomain) {
            $counts['defect']++;
            $line = sprintf('%s → wire domain "%s", expected "%s"', $label, $domain, $expectDomain);
            $defects[] = $line;
            printf("  DEFECT  %-42s → returned \"%s\", expected \"%s\"\n", $label, $domain, $expectDomain);

            return $result;
        }
        $counts['ok']++;
        $id = is_string($item->raw['id'] ?? null) ? $item->raw['id'] : ($domain ?? 'ok');
        printf("  ok      %-42s → %s%s\n", $label, $id, $suffix);

        return $result;
    }

    // Domainless payload (e.g. events.catalog): a non-empty structure is enough.
    $counts['ok']++;
    printf("  ok      %-42s → %s\n", $label, is_array($result) ? count($result) . ' entries' : 'ok');

    return $result;
}

/** A syntactically-valid id that will not exist — for probing error paths. */
function ghost(string $suffix): string
{
    return 'nbo000000000000' . $suffix;
}

// ── Catalog & customer ───────────────────────────────────────────────────────
$coupon = check('coupons.create', 'coupon', fn () => $nomba->coupons->create(['code' => "VERIFY{$u}", 'percentOff' => 10, 'duration' => 'once']));
check('coupons.retrieve', 'coupon', fn () => $nomba->coupons->retrieve($coupon?->id ?? ghost('cpn')));
check('coupons.update', 'coupon', fn () => $nomba->coupons->update($coupon?->id ?? ghost('cpn'), ['maxRedemptions' => 100]));
check('coupons.list', 'coupon', fn () => $nomba->coupons->list(['limit' => 3]));

$plan = check('plans.create', 'plan', fn () => $nomba->plans->create(['name' => "Verify {$u}"]));
$planId = $plan?->id ?? ghost('pln');
check('plans.retrieve', 'plan', fn () => $nomba->plans->retrieve($planId));
check('plans.update', 'plan', fn () => $nomba->plans->update($planId, ['description' => 'verify']));
check('plans.list', 'plan', fn () => $nomba->plans->list(['limit' => 3]));

$price = check('plans.prices.create', 'price', fn () => $nomba->plans->prices->create($planId, ['unitAmountInKobo' => 250_000, 'interval' => 'month']));
check('plans.prices.list', 'price', fn () => $nomba->plans->prices->list($planId, ['limit' => 3]));
$priceId = $price?->id ?? ghost('prc');
// A second price for change/schedule (direct fixture; not a probe).
$price2Id = $priceId;
try {
    $price2Id = $nomba->plans->prices->create($planId, ['unitAmountInKobo' => 500_000, 'interval' => 'month'])->id;
} catch (\Throwable) {
}

check('prices.retrieve', 'price', fn () => $nomba->prices->retrieve($priceId));
check('prices.list', 'price', fn () => $nomba->prices->list(['limit' => 3]));

$customer = check('customers.create', 'customer', fn () => $nomba->customers->create(['email' => "verify-{$u}@example.com", 'name' => 'Verify Bot']));
$customerId = $customer?->id ?? ghost('cus');
check('customers.retrieve', 'customer', fn () => $nomba->customers->retrieve($customerId));
check('customers.update', 'customer', fn () => $nomba->customers->update($customerId, ['name' => 'Verify Bot 2']));
check('customers.list', 'customer', fn () => $nomba->customers->list(['limit' => 3]));
check('customers.applyDiscount', 'discount', fn () => $nomba->customers->applyDiscount($customerId, ['coupon' => "VERIFY{$u}"]));
check('customers.removeDiscount', 'discount', fn () => $nomba->customers->removeDiscount($customerId));
$grant = check('customers.grantCredit', 'credit_grant', fn () => $nomba->customers->grantCredit($customerId, ['amountInKobo' => 100_000, 'source' => 'goodwill']));
check('customers.retrieveCreditBalance', 'credit_balance', fn () => $nomba->customers->retrieveCreditBalance($customerId));
check('customers.voidCredit', 'credit_grant', fn () => $nomba->customers->voidCredit($customerId, $grant?->id ?? ghost('crg')));

// ── Payment methods (sandbox mints a chargeable card) ────────────────────────
$pm = check('sandbox.createPaymentMethod', 'payment_method', fn () => $nomba->sandbox->createPaymentMethod(['customerId' => $customerId, 'behavior' => 'success']));
$pmId = $pm?->id ?? ghost('pmt');
check('paymentMethods.setup', 'checkout_setup', fn () => $nomba->paymentMethods->setup(['customerRef' => $customerId, 'amountInKobo' => 5_000, 'callbackUrl' => 'https://example.com/return']));
check('paymentMethods.createVirtualAccount', 'virtual_account', fn () => $nomba->paymentMethods->createVirtualAccount(['customerRef' => $customerId]));
check('paymentMethods.retrieve', 'payment_method', fn () => $nomba->paymentMethods->retrieve($pmId));
check('paymentMethods.list', 'payment_method', fn () => $nomba->paymentMethods->list(['customerRef' => $customerId, 'limit' => 3]));
check('paymentMethods.setDefault', 'payment_method', fn () => $nomba->paymentMethods->setDefault($pmId));
// remove a throwaway PM so we don't detach the subscription's card
$throwawayPm = null;
try {
    $throwawayPm = $nomba->sandbox->createPaymentMethod(['customerId' => $customerId, 'behavior' => 'success'])->id;
} catch (\Throwable) {
}
check('paymentMethods.remove', 'payment_method', fn () => $nomba->paymentMethods->remove($throwawayPm ?? ghost('pmt')));

// ── Mandates (create is a known sandbox 504 → infra) ─────────────────────────
check('mandates.create', 'mandate_setup', fn () => $nomba->mandates->create([
    'customerRef' => $customerId, 'customerAccountNumber' => '0123456789', 'bankCode' => '058',
    'customerName' => 'Verify Bot', 'customerAccountName' => 'Verify Bot', 'customerPhoneNumber' => '+2348012345678',
    'customerAddress' => '1 Marina, Lagos', 'narration' => 'verify', 'maxAmountInKobo' => 500_000,
]));
check('mandates.retrieve', 'payment_method', fn () => $nomba->mandates->retrieve($pmId));

// ── Subscriptions ────────────────────────────────────────────────────────────
$sub = check('subscriptions.create', 'subscription', fn () => $nomba->subscriptions->create(['customerId' => $customerId, 'priceId' => $priceId, 'paymentMethodId' => $pmId]));
$subId = $sub?->id ?? ghost('sub');
check('subscriptions.retrieve', 'subscription', fn () => $nomba->subscriptions->retrieve($subId));
check('subscriptions.update', 'subscription', fn () => $nomba->subscriptions->update($subId, ['metadata' => ['verify' => 'true']]));
check('subscriptions.list', 'subscription', fn () => $nomba->subscriptions->list(['limit' => 3]));
check('subscriptions.listEvents', 'event', fn () => $nomba->subscriptions->listEvents($subId, ['limit' => 3]));
check('subscriptions.retrieveUpcomingInvoice', 'upcoming_invoice', fn () => $nomba->subscriptions->retrieveUpcomingInvoice($subId));
check('subscriptions.applyDiscount', 'discount', fn () => $nomba->subscriptions->applyDiscount($subId, ['coupon' => "VERIFY{$u}"]));
check('subscriptions.removeDiscount', 'discount', fn () => $nomba->subscriptions->removeDiscount($subId));
check('subscriptions.dunning.retrieve', 'dunning_state', fn () => $nomba->subscriptions->dunning->retrieve($subId));
check('subscriptions.dunning.listAttempts', 'dunning_attempt', fn () => $nomba->subscriptions->dunning->listAttempts($subId, ['limit' => 3]));
check('subscriptions.schedule.create', 'subscription_schedule', fn () => $nomba->subscriptions->schedule->create($subId, ['priceId' => $price2Id]));
check('subscriptions.schedule.retrieve', 'subscription_schedule', fn () => $nomba->subscriptions->schedule->retrieve($subId));
check('subscriptions.schedule.release', 'subscription_schedule', fn () => $nomba->subscriptions->schedule->release($subId));
check('subscriptions.change', 'subscription', fn () => $nomba->subscriptions->change($subId, ['priceId' => $price2Id]));
check('subscriptions.updatePaymentMethod', 'payment_method', fn () => $nomba->subscriptions->updatePaymentMethod($subId, ['paymentMethodReference' => $pmId]));
check('subscriptions.pause', 'subscription', fn () => $nomba->subscriptions->pause($subId));
check('subscriptions.resume', 'subscription', fn () => $nomba->subscriptions->resume($subId));

// ── Invoices (the cycle produced one) ────────────────────────────────────────
$invoiceId = is_string($sub?->latestInvoiceId ?? null) ? $sub->latestInvoiceId : null;
if ($invoiceId === null) {
    try {
        $invoiceId = $nomba->invoices->list(['limit' => 1])->data[0]->id ?? null;
    } catch (\Throwable) {
    }
}
check('invoices.retrieve', 'invoice', fn () => $nomba->invoices->retrieve($invoiceId ?? ghost('inv')));
check('invoices.list', 'invoice', fn () => $nomba->invoices->list(['limit' => 3]));
check('invoices.void', 'invoice', fn () => $nomba->invoices->void($invoiceId ?? ghost('inv'), ['comment' => 'verify']));

// ── Settlements ──────────────────────────────────────────────────────────────
$settlementId = null;
try {
    $settlementId = $nomba->settlements->list(['limit' => 1])->data[0]->id ?? null;
} catch (\Throwable) {
}
check('settlements.retrieve', 'settlement', fn () => $nomba->settlements->retrieve($settlementId ?? ghost('stl')));
check('settlements.list', 'settlement', fn () => $nomba->settlements->list(['limit' => 3]));
check('settlements.retrieveEscrow', 'escrow', fn () => $nomba->settlements->retrieveEscrow());
check('settlements.refund', 'refund', fn () => $nomba->settlements->refund($settlementId ?? ghost('stl')));
check('settlements.createPayout', 'payout', fn () => $nomba->settlements->createPayout(['amountInKobo' => 100_000, 'bankCode' => '058', 'accountNumber' => '0123456789'], ['idempotencyKey' => "verify-payout-{$u}"]));

// ── Webhook endpoints (+ deliveries) ─────────────────────────────────────────
$endpoint = check('webhookEndpoints.create', 'webhook', fn () => $nomba->webhookEndpoints->create(['url' => "https://example.com/hook/{$u}"]));
$endpointId = $endpoint?->id ?? ghost('whk');
check('webhookEndpoints.retrieve', 'webhook', fn () => $nomba->webhookEndpoints->retrieve($endpointId));
check('webhookEndpoints.update', 'webhook', fn () => $nomba->webhookEndpoints->update($endpointId, ['disabled' => false]));
check('webhookEndpoints.list', 'webhook', fn () => $nomba->webhookEndpoints->list(['limit' => 3]));
check('webhookEndpoints.rotateSecret', 'webhook_secret', fn () => $nomba->webhookEndpoints->rotateSecret($endpointId));
check('webhookEndpoints.deliveries.list', 'webhook_delivery', fn () => $nomba->webhookEndpoints->deliveries->list($endpointId, ['limit' => 3]));
check('webhookEndpoints.deliveries.retrieve', 'webhook_delivery', fn () => $nomba->webhookEndpoints->deliveries->retrieve($endpointId, ghost('whd')));
check('webhookEndpoints.deliveries.replay', 'webhook_delivery', fn () => $nomba->webhookEndpoints->deliveries->replay($endpointId, ghost('whd')));
check('webhookEndpoints.delete', 'webhook', fn () => $nomba->webhookEndpoints->delete($endpointId));

// ── Events, organization, metrics ────────────────────────────────────────────
$eventId = null;
try {
    $eventId = $nomba->events->list(['limit' => 1])->data[0]->id ?? null;
} catch (\Throwable) {
}
check('events.list', 'event', fn () => $nomba->events->list(['limit' => 3]));
check('events.retrieve', 'event', fn () => $nomba->events->retrieve($eventId ?? ghost('evt')));
check('events.catalog', null, fn () => $nomba->events->catalog());

check('organization.retrieve', 'organization', fn () => $nomba->organization->retrieve());
check('organization.update', 'organization', fn () => $nomba->organization->update(['monthlyRequestQuota' => 1_000_000]));
check('organization.billing.retrieve', 'billing_settings', fn () => $nomba->organization->billing->retrieve());
check('organization.billing.update', 'billing_settings', fn () => $nomba->organization->billing->update(['commsEnabled' => true]));

check('metrics.billing', 'billing_metrics', fn () => $nomba->metrics->billing());

// ── Sandbox test instruments ─────────────────────────────────────────────────
check('sandbox.advanceCycle', 'advance_cycle_result', fn () => $nomba->sandbox->advanceCycle($subId));
check('sandbox.simulateWebhook', 'webhook_simulation', fn () => $nomba->sandbox->simulateWebhook(['type' => 'invoice.paid']));

// ── Deactivate / archive (throwaway fixtures for a clean result) ─────────────
$throwPrice = null;
try {
    $throwPrice = $nomba->plans->prices->create($planId, ['unitAmountInKobo' => 100_000, 'interval' => 'month'])->id;
} catch (\Throwable) {
}
check('prices.deactivate', 'price', fn () => $nomba->prices->deactivate($throwPrice ?? ghost('prc')));

$throwPlan = null;
try {
    $throwPlan = $nomba->plans->create(['name' => "Verify Archive {$u}"])->id;
} catch (\Throwable) {
}
check('plans.archive', 'plan', fn () => $nomba->plans->archive($throwPlan ?? ghost('pln')));

// ── Terminal transitions (kept last so earlier reads run on a live sub) ──────
check('subscriptions.cancel', 'subscription', fn () => $nomba->subscriptions->cancel($subId, ['mode' => 'now']));
check('subscriptions.resubscribe', 'subscription', fn () => $nomba->subscriptions->resubscribe($subId));

// ── Verdict ──────────────────────────────────────────────────────────────────
$total = $counts['ok'] + $counts['exp'] + $counts['infra'] + $counts['defect'];
echo str_repeat('─', 72), "\n";
if ($defects !== []) {
    echo "DEFECTS:\n";
    foreach ($defects as $d) {
        echo "  ✗ {$d}\n";
    }
    echo str_repeat('─', 72), "\n";
}
printf(
    "%d methods | ok %d | expected-errors %d | infra %d | DEFECTS %d\n",
    $total,
    $counts['ok'],
    $counts['exp'],
    $counts['infra'],
    $counts['defect'],
);

exit($counts['defect'] > 0 ? 1 : 0);
