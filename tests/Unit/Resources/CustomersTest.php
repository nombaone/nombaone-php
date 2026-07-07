<?php

declare(strict_types=1);

namespace NombaOne\Tests\Unit\Resources;

use NombaOne\Models\CreditBalance;
use NombaOne\Models\CreditGrant;
use NombaOne\Models\Customer;
use NombaOne\Models\Discount;
use NombaOne\Tests\Support\MakesTestClient;
use NombaOne\Tests\Support\RecordingHttpClient;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
final class CustomersTest extends TestCase
{
    use MakesTestClient;

    private const CUSTOMER = [
        'domain' => 'customer',
        'id' => 'nbo000000000001cus',
        'email' => 'ada@example.com',
        'name' => 'Ada Lovelace',
        'phone' => null,
        'metadata' => ['crmId' => 'crm_812'],
        'mode' => 'sandbox',
        'createdAt' => '2026-07-05T10:00:00.000Z',
        'updatedAt' => '2026-07-05T10:00:00.000Z',
    ];

    public function testCreateSendsPostAndHydratesTheCustomer(): void
    {
        $http = new RecordingHttpClient();
        $http->ok(self::CUSTOMER, status: 201);

        $customer = $this->makeClient($http)->customers->create([
            'email' => 'ada@example.com',
            'name' => 'Ada Lovelace',
        ]);

        $call = $http->calls[0];
        $this->assertSame('POST', $call->method);
        $this->assertSame('/v1/customers', $call->pathWithQuery());
        $this->assertSame(['email' => 'ada@example.com', 'name' => 'Ada Lovelace'], $call->body);
        $this->assertNotNull($call->header('Idempotency-Key'));
        $this->assertSame('application/json', $call->header('Content-Type'));
        $this->assertSame('Bearer nbo_sandbox_unit_test_key', $call->header('Authorization'));
        $this->assertSame('nombaone-php/' . \NombaOne\Version::get(), $call->header('User-Agent'));

        $this->assertInstanceOf(Customer::class, $customer);
        $this->assertSame('nbo000000000001cus', $customer->id);
        $this->assertSame('ada@example.com', $customer->email);
        $this->assertNull($customer->phone);
        $this->assertSame(['crmId' => 'crm_812'], $customer->metadata);
        $this->assertSame('req_test', $customer->requestId());
    }

    public function testRetrieveSendsGetWithEncodedId(): void
    {
        $http = new RecordingHttpClient();
        $http->ok(self::CUSTOMER);

        $this->makeClient($http)->customers->retrieve('nbo000000000001cus');

        $this->assertSame('GET', $http->calls[0]->method);
        $this->assertSame('/v1/customers/nbo000000000001cus', $http->calls[0]->pathWithQuery());
        $this->assertNull($http->calls[0]->header('Idempotency-Key'));
    }

    public function testUpdateSendsPatchWithBody(): void
    {
        $http = new RecordingHttpClient();
        $http->ok(self::CUSTOMER);

        $this->makeClient($http)->customers->update('nbo000000000001cus', ['phone' => null, 'name' => 'Ada L.']);

        $this->assertSame('PATCH', $http->calls[0]->method);
        $this->assertSame('/v1/customers/nbo000000000001cus', $http->calls[0]->pathWithQuery());
        // A user-set `phone => null` is sent (nullable-to-clear); unset fields are not.
        $this->assertSame(['phone' => null, 'name' => 'Ada L.'], $http->calls[0]->body);
    }

    public function testListSendsGetWithQueryAndReturnsAPage(): void
    {
        $http = new RecordingHttpClient();
        $http->page([self::CUSTOMER], ['hasMore' => false, 'nextCursor' => null]);

        $page = $this->makeClient($http)->customers->list(['email' => 'ada@example.com', 'limit' => 50]);

        $this->assertSame('GET', $http->calls[0]->method);
        $this->assertStringContainsString('/v1/customers?', $http->calls[0]->pathWithQuery());
        $this->assertStringContainsString('email=ada%40example.com', $http->calls[0]->pathWithQuery());
        $this->assertStringContainsString('limit=50', $http->calls[0]->pathWithQuery());
        $this->assertCount(1, $page->data);
        $this->assertInstanceOf(Customer::class, $page->data[0]);
    }

    public function testApplyDiscountSendsPostAndHydratesDiscount(): void
    {
        $http = new RecordingHttpClient();
        $http->ok([
            'domain' => 'discount',
            'id' => 'nbo000000000001dsc',
            'couponId' => 'nbo000000000001cpn',
            'customerId' => 'nbo000000000001cus',
            'subscriptionId' => null,
            'status' => 'active',
            'cyclesRemaining' => null,
            'startAt' => '2026-07-05T10:00:00.000Z',
            'endAt' => null,
            'mode' => 'sandbox',
            'createdAt' => '2026-07-05T10:00:00.000Z',
        ]);

        $discount = $this->makeClient($http)->customers->applyDiscount('nbo000000000001cus', ['coupon' => 'LAUNCH20']);

        $this->assertSame('POST', $http->calls[0]->method);
        $this->assertSame('/v1/customers/nbo000000000001cus/discount', $http->calls[0]->pathWithQuery());
        $this->assertSame(['coupon' => 'LAUNCH20'], $http->calls[0]->body);
        $this->assertInstanceOf(Discount::class, $discount);
        $this->assertSame('nbo000000000001dsc', $discount->id);
        $this->assertSame('active', $discount->status);
    }

    public function testRemoveDiscountSendsDelete(): void
    {
        $http = new RecordingHttpClient();
        $http->ok(['domain' => 'discount', 'id' => 'nbo000000000001dsc', 'status' => 'ended']);

        $this->makeClient($http)->customers->removeDiscount('nbo000000000001cus');

        $this->assertSame('DELETE', $http->calls[0]->method);
        $this->assertSame('/v1/customers/nbo000000000001cus/discount', $http->calls[0]->pathWithQuery());
    }

    public function testGrantCreditSendsPostWithIdempotencyKeyAndHydratesGrant(): void
    {
        $http = new RecordingHttpClient();
        $http->ok([
            'domain' => 'credit_grant',
            'id' => 'nbo000000000001crg',
            'customerId' => 'nbo000000000001cus',
            'amountInKobo' => 250_000,
            'remainingInKobo' => 250_000,
            'source' => 'goodwill',
            'sourceReference' => 'ticket-42',
            'mode' => 'sandbox',
            'voidedAt' => null,
            'createdAt' => '2026-07-05T10:00:00.000Z',
        ], status: 201);

        $grant = $this->makeClient($http)->customers->grantCredit('nbo000000000001cus', [
            'amountInKobo' => 250_000,
            'source' => 'goodwill',
        ]);

        $this->assertSame('POST', $http->calls[0]->method);
        $this->assertSame('/v1/customers/nbo000000000001cus/credit', $http->calls[0]->pathWithQuery());
        $this->assertSame(['amountInKobo' => 250_000, 'source' => 'goodwill'], $http->calls[0]->body);
        $this->assertNotNull($http->calls[0]->header('Idempotency-Key'));
        $this->assertInstanceOf(CreditGrant::class, $grant);
        $this->assertSame(250_000, $grant->amountInKobo);
    }

    public function testRetrieveCreditBalanceHydratesNestedGrants(): void
    {
        $http = new RecordingHttpClient();
        $http->ok([
            'domain' => 'credit_balance',
            'customerId' => 'nbo000000000001cus',
            'balanceInKobo' => 250_000,
            'grants' => [[
                'domain' => 'credit_grant',
                'id' => 'nbo000000000001crg',
                'customerId' => 'nbo000000000001cus',
                'amountInKobo' => 250_000,
                'remainingInKobo' => 250_000,
                'source' => 'manual',
                'sourceReference' => null,
                'mode' => 'sandbox',
                'voidedAt' => null,
                'createdAt' => '2026-07-05T10:00:00.000Z',
            ]],
        ]);

        $balance = $this->makeClient($http)->customers->retrieveCreditBalance('nbo000000000001cus');

        $this->assertSame('GET', $http->calls[0]->method);
        $this->assertSame('/v1/customers/nbo000000000001cus/credit', $http->calls[0]->pathWithQuery());
        $this->assertInstanceOf(CreditBalance::class, $balance);
        $this->assertSame(250_000, $balance->balanceInKobo);
        $this->assertCount(1, $balance->grants);
        $this->assertInstanceOf(CreditGrant::class, $balance->grants[0]);
        $this->assertSame('nbo000000000001crg', $balance->grants[0]->id);
    }

    public function testVoidCreditSendsDeleteToGrantSubpath(): void
    {
        $http = new RecordingHttpClient();
        $http->ok([
            'domain' => 'credit_grant',
            'id' => 'nbo000000000001crg',
            'customerId' => 'nbo000000000001cus',
            'amountInKobo' => 250_000,
            'remainingInKobo' => 0,
            'source' => 'manual',
            'sourceReference' => null,
            'mode' => 'sandbox',
            'voidedAt' => '2026-07-05T11:00:00.000Z',
            'createdAt' => '2026-07-05T10:00:00.000Z',
        ]);

        $this->makeClient($http)->customers->voidCredit('nbo000000000001cus', 'nbo000000000001crg');

        $this->assertSame('DELETE', $http->calls[0]->method);
        $this->assertSame('/v1/customers/nbo000000000001cus/credit/nbo000000000001crg', $http->calls[0]->pathWithQuery());
    }
}
