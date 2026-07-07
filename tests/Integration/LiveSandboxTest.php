<?php

declare(strict_types=1);

namespace NombaOne\Tests\Integration;

use NombaOne\Exceptions\NotFoundException;
use NombaOne\Models\Customer;
use NombaOne\Nombaone;
use NombaOne\Webhooks\WebhookEventType;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;

/**
 * The live suite. Gated on `NOMBAONE_API_KEY` — it is skipped in the default
 * `composer test` and only runs against a real NombaOne API (the deployed
 * sandbox, or a local isolated stack). Set `NOMBAONE_BASE_URL` to override the
 * host derived from the key.
 */
#[CoversNothing]
final class LiveSandboxTest extends TestCase
{
    private Nombaone $nomba;
    private string $tag;

    protected function setUp(): void
    {
        $key = getenv('NOMBAONE_API_KEY');
        if (!is_string($key) || $key === '') {
            $this->markTestSkipped('Set NOMBAONE_API_KEY to run the live integration suite.');
        }

        $options = [];
        $baseUrl = getenv('NOMBAONE_BASE_URL');
        if (is_string($baseUrl) && $baseUrl !== '') {
            $options['baseUrl'] = $baseUrl;
        }

        $this->nomba = new Nombaone($key, $options);
        $this->tag = bin2hex(random_bytes(4));
    }

    public function testFullLifecycleReachesAnActiveSubscriptionAndCancelsCleanly(): void
    {
        $customer = $this->nomba->customers->create([
            'email' => "php-it-{$this->tag}@example.com",
            'name' => 'PHP IT',
        ]);
        $this->assertStringEndsWith('cus', $customer->id);
        $this->assertNotSame('', $customer->requestId());

        $plan = $this->nomba->plans->create(['name' => "PHP IT {$this->tag}"]);
        $price = $this->nomba->plans->prices->create($plan->id, [
            'unitAmountInKobo' => 250_000,
            'interval' => 'month',
        ]);
        $this->assertSame(250_000, $price->unitAmountInKobo);
        $this->assertSame('month', $price->interval);

        $method = $this->nomba->sandbox->createPaymentMethod([
            'customerId' => $customer->id,
            'behavior' => 'success',
        ]);
        $this->assertSame('active', $method->status);

        $subscription = $this->nomba->subscriptions->create([
            'customerId' => $customer->id,
            'priceId' => $price->id,
            'paymentMethodId' => $method->id,
        ]);
        $this->assertContains($subscription->status, ['active', 'trialing']);

        // The test clock: force a cycle through the real engine.
        $cycle = $this->nomba->sandbox->advanceCycle($subscription->id);
        $this->assertSame('paid', $cycle->outcome);
        $this->assertSame('paid', $cycle->invoice->status);
        $this->assertGreaterThan(0, $cycle->invoice->totalInKobo);

        // Upcoming invoice preview + dunning read.
        $upcoming = $this->nomba->subscriptions->retrieveUpcomingInvoice($subscription->id);
        $this->assertSame('upcoming_invoice', $upcoming->domain);
        $this->assertGreaterThan(0, $upcoming->amountDueInKobo);

        $dunning = $this->nomba->subscriptions->dunning->retrieve($subscription->id);
        $this->assertSame('dunning_state', $dunning->domain);

        // Clean cancel.
        $canceled = $this->nomba->subscriptions->cancel($subscription->id);
        $this->assertSame('canceled', $canceled->status);
    }

    public function testPaginationWorksWithRealCursors(): void
    {
        $page = $this->nomba->customers->list(['limit' => 2]);
        $this->assertLessThanOrEqual(2, count($page->data));

        if ($page->hasNextPage()) {
            $next = $page->nextPage();
            $this->assertNotSame($page->requestId, $next->requestId);
        }

        // Auto-iteration across pages (bounded so the shared sandbox stays fast).
        $seen = 0;
        foreach ($this->nomba->customers->list(['limit' => 2]) as $customer) {
            $this->assertInstanceOf(Customer::class, $customer);
            if (++$seen >= 5) {
                break;
            }
        }
        $this->assertGreaterThan(0, $seen);
    }

    public function testIdempotencyReplayReturnsTheIdenticalResource(): void
    {
        $key = "php-it-replay-{$this->tag}";
        $params = ['email' => "php-it-replay-{$this->tag}@example.com", 'name' => 'Replay'];

        $first = $this->nomba->customers->create($params, ['idempotencyKey' => $key]);
        $second = $this->nomba->customers->create($params, ['idempotencyKey' => $key]);

        $this->assertSame($first->id, $second->id);
    }

    public function testTypedErrorCarriesCodeHintDocUrlAndRequestId(): void
    {
        try {
            $this->nomba->customers->retrieve('nbo000000000000cus');
            $this->fail('Expected a NotFoundException for a non-existent customer.');
        } catch (NotFoundException $e) {
            $this->assertSame('CUSTOMER_NOT_FOUND', $e->errorCode);
            $this->assertSame(404, $e->statusCode);
            $this->assertNotSame('', $e->hint);
            $this->assertStringContainsString('#CUSTOMER_NOT_FOUND', $e->docUrl);
            $this->assertNotNull($e->requestId);
        }
    }

    public function testWebhookEndpointLifecycleReturnsTheSecretOnce(): void
    {
        $endpoint = $this->nomba->webhookEndpoints->create([
            'url' => "https://example.com/php-it/{$this->tag}",
            'enabledEvents' => ['invoice.paid'],
        ]);
        $this->assertNotSame('', $endpoint->signingSecret);
        $this->assertContains('invoice.paid', $endpoint->enabledEvents);

        // The webhook helper round-trips a signed delivery (works without a listener).
        $payload = '{"id":"nbo1whd","type":"invoice.paid","event":{"id":"nbo1evt","type":"invoice.paid","createdAt":"x"},"data":{"reference":"nbo1inv"}}';
        $header = $this->nomba->webhooks->generateTestHeader($payload, $endpoint->signingSecret);
        $event = $this->nomba->webhooks->constructEvent($payload, $header, $endpoint->signingSecret);
        $this->assertSame(WebhookEventType::INVOICE_PAID, $event->type);

        // Clean up.
        $deleted = $this->nomba->webhookEndpoints->delete($endpoint->id);
        $this->assertSame($endpoint->id, $deleted->id);
    }
}
