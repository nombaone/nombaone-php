<?php

declare(strict_types=1);

/**
 * Webhook receiver — verify the signature, dedupe, then dispatch by type.
 *
 * Verification needs only the signing secret, never an API key, so this script
 * is self-contained: it signs a sample delivery the way NombaOne would, then
 * runs it through the exact same handler you would deploy.
 *
 * Run:  php examples/04-webhooks-server.php
 *
 * In a real app, mount `handleDelivery()` on a route and feed it the RAW body:
 *
 *   $event = $nomba->webhooks->constructEvent(
 *       file_get_contents('php://input'),          // RAW body — never re-serialize
 *       $_SERVER['HTTP_X_NOMBAONE_SIGNATURE'] ?? '',
 *       getenv('NOMBAONE_WEBHOOK_SECRET'),         // shown once at endpoint creation
 *   );
 *
 * Framework raw-body notes:
 *   - Laravel:  $request->getContent()
 *   - Symfony:  $request->getContent()
 *   - Slim/PSR: (string) $request->getBody()
 */

require __DIR__ . '/../vendor/autoload.php';

use NombaOne\Exceptions\WebhookVerificationException;
use NombaOne\Webhooks\WebhookEvent;
use NombaOne\Webhooks\WebhookEventType;
use NombaOne\Webhooks\Webhooks;

$webhooks = new Webhooks();
$secret = 'nbo_whsec_example_secret';

/** @var array<string, true> $processed */
$processed = [];

$handle = static function (string $rawBody, string $signature) use ($webhooks, $secret, &$processed): void {
    try {
        $event = $webhooks->constructEvent($rawBody, $signature, $secret);
    } catch (WebhookVerificationException $e) {
        echo "  rejected: {$e->getMessage()}\n";

        return;
    }

    // Delivery is at-least-once — dedupe on the event id before acting.
    if (isset($processed[$event->dedupeKey()])) {
        echo "  duplicate {$event->dedupeKey()} — already processed, ack 200\n";

        return;
    }
    $processed[$event->dedupeKey()] = true;

    dispatch($event);
};

function dispatch(WebhookEvent $event): void
{
    switch ($event->type) {
        case WebhookEventType::INVOICE_PAID:
            echo "  invoice.paid → unlock {$event->data['reference']}\n";
            break;
        case WebhookEventType::INVOICE_PAYMENT_FAILED:
            echo "  invoice.payment_failed → reason={$event->data['reason']}\n";
            break;
        case WebhookEventType::INVOICE_ACTION_REQUIRED:
            echo "  invoice.action_required → send {$event->data['checkoutLink']}\n";
            break;
        default:
            echo "  {$event->type} → (no handler)\n";
    }
}

// --- Simulate three deliveries the way NombaOne would sign them ---
$paid = '{"id":"nbo1whd","type":"invoice.paid","event":{"id":"nbo1evt","type":"invoice.paid","createdAt":"2026-07-05T10:00:00.000Z"},"data":{"reference":"nbo1inv"}}';
$failed = '{"id":"nbo2whd","type":"invoice.payment_failed","event":{"id":"nbo2evt","type":"invoice.payment_failed","createdAt":"2026-07-05T10:05:00.000Z"},"data":{"reference":"nbo2inv","reason":"insufficient_funds"}}';

echo "delivery 1 (invoice.paid):\n";
$handle($paid, $webhooks->generateTestHeader($paid, $secret));

echo "delivery 2 (invoice.payment_failed):\n";
$handle($failed, $webhooks->generateTestHeader($failed, $secret));

echo "delivery 3 (redelivery of #1 — at-least-once):\n";
$handle($paid, $webhooks->generateTestHeader($paid, $secret));

echo "delivery 4 (tampered body):\n";
$handle(str_replace('nbo1inv', 'nbo9inv', $paid), $webhooks->generateTestHeader($paid, $secret));
