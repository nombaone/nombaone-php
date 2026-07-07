<?php

declare(strict_types=1);

namespace NombaOne\Tests\Conformance;

use NombaOne\Nombaone;
use NombaOne\Tests\Support\AutoRespondingHttpClient;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;

/**
 * The drift alarm. Every SDK method is exercised against a recording transport;
 * each emitted `METHOD /v1/path` must exist in the committed OpenAPI snapshot
 * (spec/openapi.json), and every spec operation (minus the explicit exclusions)
 * must be emitted by some SDK method. Either direction failing names the route.
 */
#[CoversNothing]
final class OpenApiCoverageTest extends TestCase
{
    private const HTTP_METHODS = ['get', 'post', 'patch', 'put', 'delete'];

    /** Routes intentionally NOT in the SDK surface. */
    private const EXCLUDED = [
        'get /v1/health',
        'get /v1/openapi.json',
        'post /v1/examples',
        'get /v1/examples',
        'get /v1/examples/{id}',
    ];

    private const ID = 'nbo000000000001xxx';
    private const GRANT = 'nbo000000000002crg';
    private const DELIVERY = 'nbo000000000003whd';

    public function testEverySdkCallMatchesASpecOperationAndEveryOperationIsCovered(): void
    {
        $specOps = $this->loadSpecOps();

        $http = new AutoRespondingHttpClient();
        $client = new Nombaone('nbo_sandbox_conformance', [
            'httpClient' => $http,
            'baseUrl' => 'http://api.test',
            'maxRetries' => 0,
        ]);

        foreach (self::exercises() as $exercise) {
            $exercise($client);
        }

        $covered = [];
        $unmatched = [];
        foreach ($http->calls as $call) {
            $match = $this->matchSpecOp($call['method'], $call['path'], $specOps);
            if ($match === null) {
                $unmatched[] = "{$call['method']} {$call['path']}";
            } else {
                $covered[$match['key']] = true;
            }
        }

        $this->assertSame([], $unmatched, 'SDK emitted routes that do not exist in the spec');

        $missing = [];
        foreach ($specOps as $op) {
            if (!in_array($op['key'], self::EXCLUDED, true) && !isset($covered[$op['key']])) {
                $missing[] = $op['key'];
            }
        }
        sort($missing);
        $this->assertSame([], $missing, 'spec operations with no SDK method exercising them');

        // Belt-and-braces: every EXCLUDED entry must really exist, so a renamed
        // route can't silently hide behind the exclusion list.
        $keys = array_map(static fn (array $op): string => $op['key'], $specOps);
        foreach (self::EXCLUDED as $excluded) {
            $this->assertContains($excluded, $keys, "EXCLUDED entry no longer exists in spec: {$excluded}");
        }
    }

    /**
     * @return list<array{method: string, segments: list<string>, key: string}>
     */
    private function loadSpecOps(): array
    {
        $raw = file_get_contents(__DIR__ . '/../../spec/openapi.json');
        self::assertIsString($raw);
        $spec = json_decode($raw, true);
        self::assertIsArray($spec);
        $paths = $spec['paths'] ?? null;
        self::assertIsArray($paths);

        $ops = [];
        foreach ($paths as $path => $item) {
            if (!is_string($path) || !is_array($item)) {
                continue;
            }
            foreach (array_keys($item) as $method) {
                $method = (string) $method;
                if (!in_array($method, self::HTTP_METHODS, true)) {
                    continue;
                }
                $segments = array_values(array_filter(explode('/', $path), static fn (string $s): bool => $s !== ''));
                $ops[] = ['method' => $method, 'segments' => $segments, 'key' => "{$method} {$path}"];
            }
        }

        return $ops;
    }

    /**
     * Most-specific structural match: `{param}` matches any segment; literals win ties.
     *
     * @param list<array{method: string, segments: list<string>, key: string}> $specOps
     *
     * @return array{method: string, segments: list<string>, key: string}|null
     */
    private function matchSpecOp(string $method, string $urlPath, array $specOps): ?array
    {
        $segments = array_values(array_filter(explode('/', $urlPath), static fn (string $s): bool => $s !== ''));
        $best = null;
        $bestLiterals = -1;
        foreach ($specOps as $op) {
            if ($op['method'] !== $method || count($op['segments']) !== count($segments)) {
                continue;
            }
            $literals = 0;
            $ok = true;
            foreach ($op['segments'] as $i => $specSeg) {
                if (str_starts_with($specSeg, '{')) {
                    continue;
                }
                if ($specSeg !== $segments[$i]) {
                    $ok = false;
                    break;
                }
                $literals++;
            }
            if ($ok && $literals > $bestLiterals) {
                $best = $op;
                $bestLiterals = $literals;
            }
        }

        return $best;
    }

    /**
     * One entry per SDK method — the complete public surface (78 operations).
     *
     * @return list<\Closure(Nombaone): mixed>
     */
    private static function exercises(): array
    {
        $id = self::ID;

        return [
            // customers
            fn (Nombaone $c) => $c->customers->create(['email' => 'a@b.co', 'name' => 'A']),
            fn (Nombaone $c) => $c->customers->retrieve($id),
            fn (Nombaone $c) => $c->customers->update($id, ['name' => 'B']),
            fn (Nombaone $c) => $c->customers->list(),
            fn (Nombaone $c) => $c->customers->applyDiscount($id, ['coupon' => 'X']),
            fn (Nombaone $c) => $c->customers->removeDiscount($id),
            fn (Nombaone $c) => $c->customers->grantCredit($id, ['amountInKobo' => 100]),
            fn (Nombaone $c) => $c->customers->retrieveCreditBalance($id),
            fn (Nombaone $c) => $c->customers->voidCredit($id, self::GRANT),
            // plans (+ nested prices)
            fn (Nombaone $c) => $c->plans->create(['name' => 'Pro']),
            fn (Nombaone $c) => $c->plans->retrieve($id),
            fn (Nombaone $c) => $c->plans->update($id, ['name' => 'Pro2']),
            fn (Nombaone $c) => $c->plans->list(),
            fn (Nombaone $c) => $c->plans->archive($id),
            fn (Nombaone $c) => $c->plans->prices->create($id, ['unitAmountInKobo' => 100, 'interval' => 'month']),
            fn (Nombaone $c) => $c->plans->prices->list($id),
            // prices
            fn (Nombaone $c) => $c->prices->retrieve($id),
            fn (Nombaone $c) => $c->prices->list(),
            fn (Nombaone $c) => $c->prices->deactivate($id),
            // subscriptions
            fn (Nombaone $c) => $c->subscriptions->create(['customerId' => $id, 'priceId' => $id, 'paymentMethodId' => $id]),
            fn (Nombaone $c) => $c->subscriptions->retrieve($id),
            fn (Nombaone $c) => $c->subscriptions->update($id, ['metadata' => []]),
            fn (Nombaone $c) => $c->subscriptions->list(),
            fn (Nombaone $c) => $c->subscriptions->listEvents($id),
            fn (Nombaone $c) => $c->subscriptions->pause($id),
            fn (Nombaone $c) => $c->subscriptions->resume($id),
            fn (Nombaone $c) => $c->subscriptions->cancel($id),
            fn (Nombaone $c) => $c->subscriptions->resubscribe($id),
            fn (Nombaone $c) => $c->subscriptions->change($id, ['priceId' => $id]),
            fn (Nombaone $c) => $c->subscriptions->updatePaymentMethod($id, ['checkoutToken' => 't']),
            fn (Nombaone $c) => $c->subscriptions->retrieveUpcomingInvoice($id),
            fn (Nombaone $c) => $c->subscriptions->applyDiscount($id, ['coupon' => 'X']),
            fn (Nombaone $c) => $c->subscriptions->removeDiscount($id),
            fn (Nombaone $c) => $c->subscriptions->schedule->create($id, ['priceId' => $id]),
            fn (Nombaone $c) => $c->subscriptions->schedule->retrieve($id),
            fn (Nombaone $c) => $c->subscriptions->schedule->release($id),
            fn (Nombaone $c) => $c->subscriptions->dunning->retrieve($id),
            fn (Nombaone $c) => $c->subscriptions->dunning->listAttempts($id),
            // invoices
            fn (Nombaone $c) => $c->invoices->retrieve($id),
            fn (Nombaone $c) => $c->invoices->list(),
            fn (Nombaone $c) => $c->invoices->void($id),
            // coupons
            fn (Nombaone $c) => $c->coupons->create(['code' => 'X', 'percentOff' => 10, 'duration' => 'once']),
            fn (Nombaone $c) => $c->coupons->retrieve($id),
            fn (Nombaone $c) => $c->coupons->update($id, ['maxRedemptions' => 5]),
            fn (Nombaone $c) => $c->coupons->list(),
            // payment methods
            fn (Nombaone $c) => $c->paymentMethods->setup(['customerRef' => $id, 'amountInKobo' => 100, 'callbackUrl' => 'https://x.co']),
            fn (Nombaone $c) => $c->paymentMethods->createVirtualAccount(['customerRef' => $id]),
            fn (Nombaone $c) => $c->paymentMethods->retrieve($id),
            fn (Nombaone $c) => $c->paymentMethods->list(),
            fn (Nombaone $c) => $c->paymentMethods->setDefault($id),
            fn (Nombaone $c) => $c->paymentMethods->remove($id),
            // mandates
            fn (Nombaone $c) => $c->mandates->create([
                'customerRef' => $id, 'customerAccountNumber' => '0123456789', 'bankCode' => '058',
                'customerName' => 'A', 'customerAccountName' => 'A', 'customerPhoneNumber' => '+234',
                'customerAddress' => 'Lagos', 'narration' => 'sub', 'maxAmountInKobo' => 100,
            ]),
            fn (Nombaone $c) => $c->mandates->retrieve($id),
            // settlements
            fn (Nombaone $c) => $c->settlements->retrieve($id),
            fn (Nombaone $c) => $c->settlements->list(),
            fn (Nombaone $c) => $c->settlements->retrieveEscrow(),
            fn (Nombaone $c) => $c->settlements->refund($id),
            fn (Nombaone $c) => $c->settlements->createPayout(['amountInKobo' => 100, 'bankCode' => '058', 'accountNumber' => '01']),
            // webhook endpoints (+ deliveries)
            fn (Nombaone $c) => $c->webhookEndpoints->create(['url' => 'https://x.co/h']),
            fn (Nombaone $c) => $c->webhookEndpoints->retrieve($id),
            fn (Nombaone $c) => $c->webhookEndpoints->update($id, ['disabled' => true]),
            fn (Nombaone $c) => $c->webhookEndpoints->list(),
            fn (Nombaone $c) => $c->webhookEndpoints->delete($id),
            fn (Nombaone $c) => $c->webhookEndpoints->rotateSecret($id),
            fn (Nombaone $c) => $c->webhookEndpoints->deliveries->list($id),
            fn (Nombaone $c) => $c->webhookEndpoints->deliveries->retrieve($id, self::DELIVERY),
            fn (Nombaone $c) => $c->webhookEndpoints->deliveries->replay($id, self::DELIVERY),
            // events
            fn (Nombaone $c) => $c->events->list(),
            fn (Nombaone $c) => $c->events->retrieve($id),
            fn (Nombaone $c) => $c->events->catalog(),
            // organization
            fn (Nombaone $c) => $c->organization->retrieve(),
            fn (Nombaone $c) => $c->organization->update(['settlementMode' => 'split_at_collection']),
            fn (Nombaone $c) => $c->organization->billing->retrieve(),
            fn (Nombaone $c) => $c->organization->billing->update(['commsEnabled' => true]),
            // metrics
            fn (Nombaone $c) => $c->metrics->billing(),
            // sandbox
            fn (Nombaone $c) => $c->sandbox->createPaymentMethod(['customerId' => $id]),
            fn (Nombaone $c) => $c->sandbox->advanceCycle($id),
            fn (Nombaone $c) => $c->sandbox->simulateWebhook(['type' => 'invoice.paid']),
        ];
    }
}
