<?php

declare(strict_types=1);

namespace NombaOne\Tests\Unit\Resources;

use NombaOne\Exceptions\NombaOneException;
use NombaOne\Models\BillingMetrics;
use NombaOne\Models\PaymentMethod;
use NombaOne\Models\Settlement;
use NombaOne\Models\TenantSettings;
use NombaOne\Models\WebhookEndpointWithSecret;
use NombaOne\Nombaone;
use NombaOne\Tests\Support\MakesTestClient;
use NombaOne\Tests\Support\RecordingHttpClient;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
final class MoneyPlatformTest extends TestCase
{
    use MakesTestClient;

    /**
     * @return iterable<string, array{\Closure(Nombaone): mixed, string, string, bool}>
     */
    public static function routes(): iterable
    {
        // Payment methods
        yield 'paymentMethods.setup' => [fn (Nombaone $c) => $c->paymentMethods->setup(['customerRef' => 'cus1', 'amountInKobo' => 100, 'callbackUrl' => 'https://x.co']), 'POST', '/v1/payment-methods/setup', false];
        yield 'paymentMethods.createVirtualAccount' => [fn (Nombaone $c) => $c->paymentMethods->createVirtualAccount(['customerRef' => 'cus1']), 'POST', '/v1/payment-methods/virtual-account', false];
        yield 'paymentMethods.retrieve' => [fn (Nombaone $c) => $c->paymentMethods->retrieve('pm1'), 'GET', '/v1/payment-methods/pm1', false];
        yield 'paymentMethods.list' => [fn (Nombaone $c) => $c->paymentMethods->list(), 'GET', '/v1/payment-methods', true];
        yield 'paymentMethods.setDefault' => [fn (Nombaone $c) => $c->paymentMethods->setDefault('pm1'), 'POST', '/v1/payment-methods/pm1/default', false];
        yield 'paymentMethods.remove' => [fn (Nombaone $c) => $c->paymentMethods->remove('pm1'), 'DELETE', '/v1/payment-methods/pm1', false];

        // Mandates
        yield 'mandates.create' => [fn (Nombaone $c) => $c->mandates->create(['customerRef' => 'cus1', 'customerAccountNumber' => '01', 'bankCode' => '058', 'customerName' => 'A', 'customerAccountName' => 'A', 'customerPhoneNumber' => '+234', 'customerAddress' => 'Lagos', 'narration' => 'sub', 'maxAmountInKobo' => 100]), 'POST', '/v1/mandates', false];
        yield 'mandates.retrieve' => [fn (Nombaone $c) => $c->mandates->retrieve('m1'), 'GET', '/v1/mandates/m1', false];

        // Settlements
        yield 'settlements.retrieve' => [fn (Nombaone $c) => $c->settlements->retrieve('st1'), 'GET', '/v1/settlements/st1', false];
        yield 'settlements.list' => [fn (Nombaone $c) => $c->settlements->list(), 'GET', '/v1/settlements', true];
        yield 'settlements.retrieveEscrow' => [fn (Nombaone $c) => $c->settlements->retrieveEscrow(), 'GET', '/v1/settlements/escrow', false];
        yield 'settlements.refund' => [fn (Nombaone $c) => $c->settlements->refund('st1'), 'POST', '/v1/settlements/st1/refund', false];
        yield 'settlements.createPayout' => [fn (Nombaone $c) => $c->settlements->createPayout(['amountInKobo' => 100, 'bankCode' => '058', 'accountNumber' => '01']), 'POST', '/v1/settlements/payout', false];

        // Webhook endpoints (+ deliveries)
        yield 'webhookEndpoints.create' => [fn (Nombaone $c) => $c->webhookEndpoints->create(['url' => 'https://x.co/h']), 'POST', '/v1/webhooks', false];
        yield 'webhookEndpoints.retrieve' => [fn (Nombaone $c) => $c->webhookEndpoints->retrieve('wh1'), 'GET', '/v1/webhooks/wh1', false];
        yield 'webhookEndpoints.update' => [fn (Nombaone $c) => $c->webhookEndpoints->update('wh1', ['disabled' => true]), 'PATCH', '/v1/webhooks/wh1', false];
        yield 'webhookEndpoints.list' => [fn (Nombaone $c) => $c->webhookEndpoints->list(), 'GET', '/v1/webhooks', true];
        yield 'webhookEndpoints.delete' => [fn (Nombaone $c) => $c->webhookEndpoints->delete('wh1'), 'DELETE', '/v1/webhooks/wh1', false];
        yield 'webhookEndpoints.rotateSecret' => [fn (Nombaone $c) => $c->webhookEndpoints->rotateSecret('wh1'), 'POST', '/v1/webhooks/wh1/rotate-secret', false];
        yield 'webhookEndpoints.deliveries.list' => [fn (Nombaone $c) => $c->webhookEndpoints->deliveries->list('wh1'), 'GET', '/v1/webhooks/wh1/deliveries', true];
        yield 'webhookEndpoints.deliveries.retrieve' => [fn (Nombaone $c) => $c->webhookEndpoints->deliveries->retrieve('wh1', 'd1'), 'GET', '/v1/webhooks/wh1/deliveries/d1', false];
        yield 'webhookEndpoints.deliveries.replay' => [fn (Nombaone $c) => $c->webhookEndpoints->deliveries->replay('wh1', 'd1'), 'POST', '/v1/webhooks/wh1/deliveries/d1/replay', false];

        // Events
        yield 'events.list' => [fn (Nombaone $c) => $c->events->list(), 'GET', '/v1/events', true];
        yield 'events.retrieve' => [fn (Nombaone $c) => $c->events->retrieve('e1'), 'GET', '/v1/events/e1', false];
        yield 'events.catalog' => [fn (Nombaone $c) => $c->events->catalog(), 'GET', '/v1/events/catalog', false];

        // Organization (+ billing)
        yield 'organization.retrieve' => [fn (Nombaone $c) => $c->organization->retrieve(), 'GET', '/v1/organization', false];
        yield 'organization.update' => [fn (Nombaone $c) => $c->organization->update(['settlementMode' => 'split_at_collection']), 'PUT', '/v1/organization', false];
        yield 'organization.billing.retrieve' => [fn (Nombaone $c) => $c->organization->billing->retrieve(), 'GET', '/v1/organization/billing', false];
        yield 'organization.billing.update' => [fn (Nombaone $c) => $c->organization->billing->update(['commsEnabled' => true]), 'PUT', '/v1/organization/billing', false];

        // Metrics
        yield 'metrics.billing' => [fn (Nombaone $c) => $c->metrics->billing(), 'GET', '/v1/metrics/billing', false];

        // Sandbox
        yield 'sandbox.createPaymentMethod' => [fn (Nombaone $c) => $c->sandbox->createPaymentMethod(['customerId' => 'cus1']), 'POST', '/v1/sandbox/payment-methods', false];
        yield 'sandbox.advanceCycle' => [fn (Nombaone $c) => $c->sandbox->advanceCycle('s1'), 'POST', '/v1/sandbox/subscriptions/s1/advance-cycle', false];
        yield 'sandbox.simulateWebhook' => [fn (Nombaone $c) => $c->sandbox->simulateWebhook(['type' => 'invoice.paid']), 'POST', '/v1/sandbox/webhooks/simulate', false];
    }

    /**
     * @param \Closure(Nombaone): mixed $invoke
     */
    #[DataProvider('routes')]
    public function testRouteMapsToTheCorrectMethodAndPath(\Closure $invoke, string $verb, string $path, bool $isList): void
    {
        $http = new RecordingHttpClient();
        if ($isList) {
            $http->page([], ['hasMore' => false, 'nextCursor' => null]);
        } else {
            $http->ok([]);
        }

        $invoke($this->makeClient($http));

        $this->assertSame($verb, $http->calls[0]->method);
        $this->assertSame($path, $http->calls[0]->pathWithQuery());
    }

    public function testSandboxMethodsThrowLocallyWithALiveKeyAndSendNoRequest(): void
    {
        $http = new RecordingHttpClient();
        $client = new Nombaone('nbo_live_key', ['httpClient' => $http, 'baseUrl' => 'http://api.test']);

        try {
            $client->sandbox->advanceCycle('s1');
            $this->fail('Expected a NombaOneException from a sandbox call with a live key.');
        } catch (NombaOneException $e) {
            $this->assertStringContainsString('sandbox key', $e->getMessage());
            $this->assertCount(0, $http->calls);
        }
    }

    public function testPaymentMethodListUsesCustomerRefFilter(): void
    {
        $http = new RecordingHttpClient();
        $http->page([], ['hasMore' => false, 'nextCursor' => null]);

        $this->makeClient($http)->paymentMethods->list(['customerRef' => 'nbo1cus']);

        $this->assertStringContainsString('customerRef=nbo1cus', $http->calls[0]->pathWithQuery());
    }

    public function testMandateRetrieveReturnsAPaymentMethod(): void
    {
        $http = new RecordingHttpClient();
        $http->ok([
            'domain' => 'payment_method',
            'id' => 'nbo1pmt',
            'customerId' => 'nbo1cus',
            'kind' => 'mandate',
            'status' => 'consent_pending',
            'isDefault' => false,
            'brand' => null,
            'last4' => null,
            'expMonth' => null,
            'expYear' => null,
            'mode' => 'sandbox',
            'createdAt' => '2026-07-05T10:00:00.000Z',
            'updatedAt' => '2026-07-05T10:00:00.000Z',
        ]);

        $method = $this->makeClient($http)->mandates->retrieve('nbo1pmt');

        $this->assertInstanceOf(PaymentMethod::class, $method);
        $this->assertSame('mandate', $method->kind);
        $this->assertSame('consent_pending', $method->status);
    }

    public function testWebhookEndpointCreateExposesSigningSecretOnce(): void
    {
        $http = new RecordingHttpClient();
        $http->ok([
            'domain' => 'webhook',
            'id' => 'nbo1whk',
            'url' => 'https://x.co/h',
            'enabledEvents' => ['invoice.paid', 'invoice.payment_failed'],
            'signingSecret' => 'nbo_whsec_abc123',
            'signingSecretPrefix' => 'nbo_whsec_abc',
            'disabledAt' => null,
            'createdAt' => '2026-07-05T10:00:00.000Z',
        ], status: 201);

        $endpoint = $this->makeClient($http)->webhookEndpoints->create(['url' => 'https://x.co/h']);

        $this->assertInstanceOf(WebhookEndpointWithSecret::class, $endpoint);
        $this->assertSame('nbo_whsec_abc123', $endpoint->signingSecret);
        $this->assertSame(['invoice.paid', 'invoice.payment_failed'], $endpoint->enabledEvents);
    }

    public function testSettlementHydration(): void
    {
        $http = new RecordingHttpClient();
        $http->ok([
            'domain' => 'settlement',
            'id' => 'nbo1stl',
            'invoiceReference' => 'nbo1inv',
            'subAccountRef' => 'sub_1',
            'splitReference' => null,
            'merchantTxRef' => 'mtx_1',
            'grossInKobo' => 250_000,
            'platformFeeInKobo' => 5_000,
            'netToTenantInKobo' => 245_000,
            'status' => 'settled',
            'createdAt' => '2026-07-05T10:00:00.000Z',
        ]);

        $settlement = $this->makeClient($http)->settlements->retrieve('nbo1stl');

        $this->assertInstanceOf(Settlement::class, $settlement);
        $this->assertSame(245_000, $settlement->netToTenantInKobo);
        $this->assertSame('settled', $settlement->status);
    }

    public function testTenantSettingsExposesNestedBlocks(): void
    {
        $http = new RecordingHttpClient();
        $http->ok([
            'domain' => 'organization',
            'billing' => ['settlementMode' => 'split_at_collection', 'rateLimitPerMinute' => 60],
            'webhook' => ['url' => 'https://x.co/h', 'signingSecretPrefix' => 'nbo_whsec_abc', 'configured' => true],
            'nombaAccount' => ['accountRef' => 'acc_1', 'status' => 'active'],
        ]);

        $settings = $this->makeClient($http)->organization->retrieve();

        $this->assertInstanceOf(TenantSettings::class, $settings);
        $this->assertSame('split_at_collection', $settings->billing['settlementMode']);
        $this->assertTrue($settings->webhook['configured']);
        $this->assertSame('acc_1', $settings->nombaAccount['accountRef']);
    }

    public function testBillingMetricsHydratesNestedFunnel(): void
    {
        $http = new RecordingHttpClient();
        $http->ok([
            'domain' => 'billing_metrics',
            'mrrInKobo' => 5_000_000,
            'activeCount' => 42,
            'voluntaryChurn' => 1,
            'involuntaryChurn' => 2,
            'failedChargeRate' => 0.12,
            'dunningRecoveryRate' => 0.71,
            'dunningFunnel' => [
                'scheduled' => 5, 'attempting' => 3, 'cardUpdateRequired' => 1,
                'rescheduled' => 2, 'succeeded' => 4, 'exhausted' => 1,
            ],
            'windowFrom' => '2026-06-05T00:00:00.000Z',
            'windowTo' => '2026-07-05T00:00:00.000Z',
        ]);

        $metrics = $this->makeClient($http)->metrics->billing();

        $this->assertInstanceOf(BillingMetrics::class, $metrics);
        $this->assertSame(5_000_000, $metrics->mrrInKobo);
        $this->assertSame(0.71, $metrics->dunningRecoveryRate);
        $this->assertSame(4, $metrics->dunningFunnel->succeeded);
    }

    public function testEventsCatalogReturnsTheRawMap(): void
    {
        $http = new RecordingHttpClient();
        $http->ok([
            'invoice.paid' => ['when' => 'An invoice is fully paid', 'payload' => ['reference']],
            'coupon.created' => ['when' => 'A coupon is created', 'payload' => ['reference', 'code']],
        ]);

        $catalog = $this->makeClient($http)->events->catalog();

        $this->assertSame('GET', $http->calls[0]->method);
        $this->assertSame('/v1/events/catalog', $http->calls[0]->pathWithQuery());
        $this->assertArrayHasKey('invoice.paid', $catalog);
        $this->assertArrayHasKey('coupon.created', $catalog);
    }
}
