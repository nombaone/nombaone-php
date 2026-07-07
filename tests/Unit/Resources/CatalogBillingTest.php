<?php

declare(strict_types=1);

namespace NombaOne\Tests\Unit\Resources;

use NombaOne\Models\DunningState;
use NombaOne\Models\Invoice;
use NombaOne\Models\Price;
use NombaOne\Models\Subscription;
use NombaOne\Models\SubscriptionSchedule;
use NombaOne\Models\UpcomingInvoice;
use NombaOne\Nombaone;
use NombaOne\Tests\Support\MakesTestClient;
use NombaOne\Tests\Support\RecordingHttpClient;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
final class CatalogBillingTest extends TestCase
{
    use MakesTestClient;

    /**
     * @return iterable<string, array{\Closure(Nombaone): mixed, string, string, bool}>
     */
    public static function routes(): iterable
    {
        // Plans (+ nested prices)
        yield 'plans.create' => [fn (Nombaone $c) => $c->plans->create(['name' => 'Pro']), 'POST', '/v1/plans', false];
        yield 'plans.retrieve' => [fn (Nombaone $c) => $c->plans->retrieve('p1'), 'GET', '/v1/plans/p1', false];
        yield 'plans.update' => [fn (Nombaone $c) => $c->plans->update('p1', ['name' => 'X']), 'PATCH', '/v1/plans/p1', false];
        yield 'plans.list' => [fn (Nombaone $c) => $c->plans->list(), 'GET', '/v1/plans', true];
        yield 'plans.archive' => [fn (Nombaone $c) => $c->plans->archive('p1'), 'POST', '/v1/plans/p1/archive', false];
        yield 'plans.prices.create' => [fn (Nombaone $c) => $c->plans->prices->create('p1', ['unitAmountInKobo' => 100, 'interval' => 'month']), 'POST', '/v1/plans/p1/prices', false];
        yield 'plans.prices.list' => [fn (Nombaone $c) => $c->plans->prices->list('p1'), 'GET', '/v1/plans/p1/prices', true];

        // Prices
        yield 'prices.retrieve' => [fn (Nombaone $c) => $c->prices->retrieve('pr1'), 'GET', '/v1/prices/pr1', false];
        yield 'prices.list' => [fn (Nombaone $c) => $c->prices->list(), 'GET', '/v1/prices', true];
        yield 'prices.deactivate' => [fn (Nombaone $c) => $c->prices->deactivate('pr1'), 'POST', '/v1/prices/pr1/deactivate', false];

        // Subscriptions
        yield 'subscriptions.create' => [fn (Nombaone $c) => $c->subscriptions->create(['customerId' => 'cus1', 'priceId' => 'pr1']), 'POST', '/v1/subscriptions', false];
        yield 'subscriptions.retrieve' => [fn (Nombaone $c) => $c->subscriptions->retrieve('s1'), 'GET', '/v1/subscriptions/s1', false];
        yield 'subscriptions.update' => [fn (Nombaone $c) => $c->subscriptions->update('s1', ['metadata' => []]), 'PATCH', '/v1/subscriptions/s1', false];
        yield 'subscriptions.list' => [fn (Nombaone $c) => $c->subscriptions->list(), 'GET', '/v1/subscriptions', true];
        yield 'subscriptions.listEvents' => [fn (Nombaone $c) => $c->subscriptions->listEvents('s1'), 'GET', '/v1/subscriptions/s1/events', true];
        yield 'subscriptions.pause' => [fn (Nombaone $c) => $c->subscriptions->pause('s1'), 'POST', '/v1/subscriptions/s1/pause', false];
        yield 'subscriptions.resume' => [fn (Nombaone $c) => $c->subscriptions->resume('s1'), 'POST', '/v1/subscriptions/s1/resume', false];
        yield 'subscriptions.cancel' => [fn (Nombaone $c) => $c->subscriptions->cancel('s1'), 'POST', '/v1/subscriptions/s1/cancel', false];
        yield 'subscriptions.resubscribe' => [fn (Nombaone $c) => $c->subscriptions->resubscribe('s1'), 'POST', '/v1/subscriptions/s1/resubscribe', false];
        yield 'subscriptions.change' => [fn (Nombaone $c) => $c->subscriptions->change('s1', ['priceId' => 'pr2']), 'POST', '/v1/subscriptions/s1/change', false];
        yield 'subscriptions.updatePaymentMethod' => [fn (Nombaone $c) => $c->subscriptions->updatePaymentMethod('s1', ['checkoutToken' => 't']), 'POST', '/v1/subscriptions/s1/payment-method', false];
        yield 'subscriptions.retrieveUpcomingInvoice' => [fn (Nombaone $c) => $c->subscriptions->retrieveUpcomingInvoice('s1'), 'GET', '/v1/subscriptions/s1/upcoming-invoice', false];
        yield 'subscriptions.applyDiscount' => [fn (Nombaone $c) => $c->subscriptions->applyDiscount('s1', ['coupon' => 'X']), 'POST', '/v1/subscriptions/s1/discount', false];
        yield 'subscriptions.removeDiscount' => [fn (Nombaone $c) => $c->subscriptions->removeDiscount('s1'), 'DELETE', '/v1/subscriptions/s1/discount', false];
        yield 'subscriptions.schedule.create' => [fn (Nombaone $c) => $c->subscriptions->schedule->create('s1', ['priceId' => 'pr2']), 'POST', '/v1/subscriptions/s1/schedule', false];
        yield 'subscriptions.schedule.retrieve' => [fn (Nombaone $c) => $c->subscriptions->schedule->retrieve('s1'), 'GET', '/v1/subscriptions/s1/schedule', false];
        yield 'subscriptions.schedule.release' => [fn (Nombaone $c) => $c->subscriptions->schedule->release('s1'), 'DELETE', '/v1/subscriptions/s1/schedule', false];
        yield 'subscriptions.dunning.retrieve' => [fn (Nombaone $c) => $c->subscriptions->dunning->retrieve('s1'), 'GET', '/v1/subscriptions/s1/dunning', false];
        yield 'subscriptions.dunning.listAttempts' => [fn (Nombaone $c) => $c->subscriptions->dunning->listAttempts('s1'), 'GET', '/v1/subscriptions/s1/dunning/attempts', true];

        // Invoices
        yield 'invoices.retrieve' => [fn (Nombaone $c) => $c->invoices->retrieve('i1'), 'GET', '/v1/invoices/i1', false];
        yield 'invoices.list' => [fn (Nombaone $c) => $c->invoices->list(), 'GET', '/v1/invoices', true];
        yield 'invoices.void' => [fn (Nombaone $c) => $c->invoices->void('i1'), 'POST', '/v1/invoices/i1/void', false];

        // Coupons
        yield 'coupons.create' => [fn (Nombaone $c) => $c->coupons->create(['code' => 'X', 'percentOff' => 10, 'duration' => 'once']), 'POST', '/v1/coupons', false];
        yield 'coupons.retrieve' => [fn (Nombaone $c) => $c->coupons->retrieve('c1'), 'GET', '/v1/coupons/c1', false];
        yield 'coupons.update' => [fn (Nombaone $c) => $c->coupons->update('c1', ['maxRedemptions' => 5]), 'PATCH', '/v1/coupons/c1', false];
        yield 'coupons.list' => [fn (Nombaone $c) => $c->coupons->list(), 'GET', '/v1/coupons', true];
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

    public function testPricesListUsesPlanRefFilterNotPlanId(): void
    {
        $http = new RecordingHttpClient();
        $http->page([], ['hasMore' => false, 'nextCursor' => null]);

        $this->makeClient($http)->prices->list(['planRef' => 'nbo1pln', 'active' => true]);

        $query = $http->calls[0]->pathWithQuery();
        $this->assertStringContainsString('planRef=nbo1pln', $query);
        $this->assertStringContainsString('active=true', $query);
        $this->assertStringNotContainsString('planId', $query);
    }

    public function testPlanPriceCreateSendsBodyAndHydratesPrice(): void
    {
        $http = new RecordingHttpClient();
        $http->ok([
            'domain' => 'price',
            'id' => 'nbo1prc',
            'planId' => 'nbo1pln',
            'unitAmountInKobo' => 250_000,
            'currency' => 'NGN',
            'interval' => 'month',
            'intervalCount' => 1,
            'usageType' => 'licensed',
            'billingScheme' => 'per_unit',
            'trialPeriodDays' => 0,
            'active' => true,
            'metadata' => [],
            'mode' => 'sandbox',
            'createdAt' => '2026-07-05T10:00:00.000Z',
        ], status: 201);

        $price = $this->makeClient($http)->plans->prices->create('nbo1pln', [
            'unitAmountInKobo' => 250_000,
            'interval' => 'month',
        ]);

        $this->assertSame(['unitAmountInKobo' => 250_000, 'interval' => 'month'], $http->calls[0]->body);
        $this->assertInstanceOf(Price::class, $price);
        $this->assertSame(250_000, $price->unitAmountInKobo);
        $this->assertSame('month', $price->interval);
        $this->assertTrue($price->active);
    }

    public function testSubscriptionCreateSendsBodyWithIdempotencyKeyAndHydratesItems(): void
    {
        $http = new RecordingHttpClient();
        $http->ok([
            'domain' => 'subscription',
            'id' => 'nbo1sub',
            'customerId' => 'nbo1cus',
            'priceId' => 'nbo1prc',
            'status' => 'active',
            'collectionMethod' => 'charge_automatically',
            'currentPeriodIndex' => 0,
            'items' => [['id' => 'si_1', 'priceId' => 'nbo1prc', 'quantity' => 2]],
            'currency' => 'NGN',
            'mode' => 'sandbox',
            'createdAt' => '2026-07-05T10:00:00.000Z',
        ], status: 201);

        $subscription = $this->makeClient($http)->subscriptions->create([
            'customerId' => 'nbo1cus',
            'priceId' => 'nbo1prc',
            'paymentMethodId' => 'nbo1pmt',
        ]);

        $this->assertNotNull($http->calls[0]->header('Idempotency-Key'));
        $this->assertSame([
            'customerId' => 'nbo1cus',
            'priceId' => 'nbo1prc',
            'paymentMethodId' => 'nbo1pmt',
        ], $http->calls[0]->body);
        $this->assertInstanceOf(Subscription::class, $subscription);
        $this->assertSame('active', $subscription->status);
        $this->assertCount(1, $subscription->items);
        $this->assertSame(2, $subscription->items[0]->quantity);
    }

    public function testInvoiceHydratesNestedLineItems(): void
    {
        $http = new RecordingHttpClient();
        $http->ok([
            'domain' => 'invoice',
            'id' => 'nbo1inv',
            'customerId' => 'nbo1cus',
            'subscriptionId' => 'nbo1sub',
            'status' => 'open',
            'billingReason' => 'subscription_cycle',
            'subtotalInKobo' => 250_000,
            'totalInKobo' => 250_000,
            'amountDueInKobo' => 250_000,
            'currency' => 'NGN',
            'lineItems' => [['id' => 'li_1', 'kind' => 'subscription', 'description' => 'Pro', 'amountInKobo' => 250_000, 'quantity' => 1]],
            'mode' => 'sandbox',
            'createdAt' => '2026-07-05T10:00:00.000Z',
        ]);

        $invoice = $this->makeClient($http)->invoices->retrieve('nbo1inv');

        $this->assertInstanceOf(Invoice::class, $invoice);
        $this->assertSame(250_000, $invoice->totalInKobo);
        $this->assertCount(1, $invoice->lineItems);
        $this->assertSame('subscription', $invoice->lineItems[0]->kind);
    }

    public function testDunningStateHydratesNestedAttempts(): void
    {
        $http = new RecordingHttpClient();
        $http->ok([
            'domain' => 'dunning_state',
            'subscriptionRef' => 'nbo1sub',
            'invoiceRef' => 'nbo1inv',
            'status' => 'attempting',
            'attemptsUsed' => 1,
            'maxAttempts' => 4,
            'nextAttemptAt' => '2026-07-06T10:00:00.000Z',
            'graceAccessUntil' => '2026-07-10T10:00:00.000Z',
            'attempts' => [[
                'domain' => 'dunning_attempt',
                'id' => 'nbo1dun',
                'attemptNumber' => 1,
                'status' => 'rescheduled',
                'branch' => 'reschedule',
                'scheduledAt' => '2026-07-05T10:00:00.000Z',
            ]],
        ]);

        $state = $this->makeClient($http)->subscriptions->dunning->retrieve('nbo1sub');

        $this->assertInstanceOf(DunningState::class, $state);
        $this->assertSame('2026-07-10T10:00:00.000Z', $state->graceAccessUntil);
        $this->assertCount(1, $state->attempts);
        $this->assertSame('reschedule', $state->attempts[0]->branch);
    }

    public function testScheduleHydratesNestedPhasesAndUpcomingInvoiceLineItems(): void
    {
        $http = new RecordingHttpClient();
        $http->ok([
            'domain' => 'subscription_schedule',
            'id' => 'nbo1sch',
            'subscriptionId' => 'nbo1sub',
            'status' => 'active',
            'phases' => [['startIndex' => 1, 'priceId' => 'nbo2prc', 'quantity' => null, 'consumedAt' => null]],
            'mode' => 'sandbox',
            'createdAt' => '2026-07-05T10:00:00.000Z',
            'updatedAt' => '2026-07-05T10:00:00.000Z',
        ])->ok([
            'domain' => 'upcoming_invoice',
            'subscriptionId' => 'nbo1sub',
            'periodIndex' => 1,
            'periodStart' => '2026-08-05T10:00:00.000Z',
            'periodEnd' => '2026-09-05T10:00:00.000Z',
            'billingReason' => 'subscription_cycle',
            'subtotalInKobo' => 250_000,
            'totalInKobo' => 250_000,
            'amountDueInKobo' => 250_000,
            'currency' => 'NGN',
            'lineItems' => [['id' => 'li_1', 'kind' => 'subscription', 'description' => 'Pro', 'amountInKobo' => 250_000, 'quantity' => 1]],
            'mode' => 'sandbox',
        ]);

        $client = $this->makeClient($http);
        $schedule = $client->subscriptions->schedule->retrieve('nbo1sub');
        $upcoming = $client->subscriptions->retrieveUpcomingInvoice('nbo1sub');

        $this->assertInstanceOf(SubscriptionSchedule::class, $schedule);
        $this->assertCount(1, $schedule->phases);
        $this->assertSame('nbo2prc', $schedule->phases[0]->priceId);
        $this->assertInstanceOf(UpcomingInvoice::class, $upcoming);
        $this->assertCount(1, $upcoming->lineItems);
        $this->assertSame(250_000, $upcoming->amountDueInKobo);
    }
}
