<?php

declare(strict_types=1);

/**
 * Sandbox dunning rehearsal — watch a thin-balance charge fail and enter
 * recovery, using a declining test card, a trial (so the first charge is
 * deferred), and the test clock.
 *
 * Run:  NOMBAONE_API_KEY=nbo_sandbox_… php examples/05-sandbox-cycle.php
 */

require __DIR__ . '/../vendor/autoload.php';

use NombaOne\Nombaone;

$nomba = new Nombaone(getenv('NOMBAONE_API_KEY'));
$tag = bin2hex(random_bytes(3));

$customer = $nomba->customers->create(['email' => "dunning-{$tag}@example.com", 'name' => 'Katherine Johnson']);
$plan = $nomba->plans->create(['name' => "Dunning {$tag}"]);
$price = $nomba->plans->prices->create($plan->id, ['unitAmountInKobo' => 250_000, 'interval' => 'month']);

// A card that declines like a thin balance does — "not yet", not "no".
$method = $nomba->sandbox->createPaymentMethod([
    'customerId' => $customer->id,
    'behavior' => 'decline_insufficient_funds',
]);

// A trial defers the first charge, so the subscription starts cleanly; the
// decline happens when the trial's cycle is forced through the engine.
$subscription = $nomba->subscriptions->create([
    'customerId' => $customer->id,
    'priceId' => $price->id,
    'paymentMethodId' => $method->id,
    'trialDays' => 7,
]);
echo "subscription {$subscription->id}  status={$subscription->status}\n";

// The test clock: run the cycle now. The charge attempt fails and recovery begins.
$cycle = $nomba->sandbox->advanceCycle($subscription->id);
echo "advance-cycle outcome={$cycle->outcome}  invoice={$cycle->invoice->id} status={$cycle->invoice->status}\n";

// past_due is NOT canceled — read dunning and honor graceAccessUntil.
$dunning = $nomba->subscriptions->dunning->retrieve($subscription->id);
echo "dunning status={$dunning->status}  attempts={$dunning->attemptsUsed}/{$dunning->maxAttempts}"
    . '  graceAccessUntil=' . ($dunning->graceAccessUntil ?? 'n/a') . "\n";

foreach ($dunning->attempts as $attempt) {
    echo "  attempt #{$attempt->attemptNumber}  status={$attempt->status}  branch={$attempt->branch}\n";
}
