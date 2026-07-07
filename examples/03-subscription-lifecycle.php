<?php

declare(strict_types=1);

/**
 * Subscription lifecycle — create, upgrade (prorated), then cancel.
 *
 * Run:  NOMBAONE_API_KEY=nbo_sandbox_… php examples/03-subscription-lifecycle.php
 */

require __DIR__ . '/../vendor/autoload.php';

use NombaOne\Nombaone;

$nomba = new Nombaone(getenv('NOMBAONE_API_KEY'));
$tag = bin2hex(random_bytes(3));

$customer = $nomba->customers->create(['email' => "lifecycle-{$tag}@example.com", 'name' => 'Grace Hopper']);
$plan = $nomba->plans->create(['name' => "Lifecycle {$tag}"]);

$starter = $nomba->plans->prices->create($plan->id, ['unitAmountInKobo' => 250_000, 'interval' => 'month']);
$pro = $nomba->plans->prices->create($plan->id, ['unitAmountInKobo' => 500_000, 'interval' => 'month']);

$method = $nomba->sandbox->createPaymentMethod(['customerId' => $customer->id]);

$subscription = $nomba->subscriptions->create([
    'customerId' => $customer->id,
    'priceId' => $starter->id,
    'paymentMethodId' => $method->id,
]);
echo "created   {$subscription->id}  status={$subscription->status}  price={$subscription->priceId}\n";

// Upgrade mid-cycle — prorated onto the next invoice by default.
$upgraded = $nomba->subscriptions->change($subscription->id, ['priceId' => $pro->id]);
echo "upgraded  price={$upgraded->priceId}\n";

// Preview what the next invoice will look like — nothing is charged.
$upcoming = $nomba->subscriptions->retrieveUpcomingInvoice($subscription->id);
echo 'upcoming  amountDue=₦' . ($upcoming->amountDueInKobo / 100) . '  lines=' . count($upcoming->lineItems) . "\n";

// Cancel at period end — the subscriber keeps access until the cycle closes.
$canceled = $nomba->subscriptions->cancel($subscription->id, ['mode' => 'at_period_end']);
echo "canceled  status={$canceled->status}  cancelAtPeriodEnd=" . ($canceled->cancelAtPeriodEnd ? 'true' : 'false') . "\n";
