<?php

declare(strict_types=1);

/**
 * Quickstart — from an API key to a live subscription in one script.
 *
 * Run:  NOMBAONE_API_KEY=nbo_sandbox_… php examples/01-quickstart.php
 */

require __DIR__ . '/../vendor/autoload.php';

use NombaOne\Nombaone;

$nomba = new Nombaone(getenv('NOMBAONE_API_KEY'));
echo "Talking to {$nomba->baseUrl} ({$nomba->mode->value})\n";

// You are three objects away from a live subscription.
$plan = $nomba->plans->create(['name' => 'Pro ' . bin2hex(random_bytes(3))]);
$price = $nomba->plans->prices->create($plan->id, [
    'unitAmountInKobo' => 250_000, // ₦2,500.00 per month — integer kobo, never floats
    'interval' => 'month',
]);
$customer = $nomba->customers->create([
    'email' => 'ada-' . bin2hex(random_bytes(3)) . '@example.com',
    'name' => 'Ada Lovelace',
]);

// Sandbox: mint a deterministic test card, then subscribe.
$method = $nomba->sandbox->createPaymentMethod(['customerId' => $customer->id]);
$subscription = $nomba->subscriptions->create([
    'customerId' => $customer->id,
    'priceId' => $price->id,
    'paymentMethodId' => $method->id,
]);

echo "plan        {$plan->id}\n";
echo "price       {$price->id}  (₦" . ($price->unitAmountInKobo / 100) . "/{$price->interval})\n";
echo "customer    {$customer->id}\n";
echo "method      {$method->id}  ({$method->kind}, {$method->status})\n";
echo "subscription {$subscription->id}  status={$subscription->status}\n";
