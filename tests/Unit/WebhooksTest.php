<?php

declare(strict_types=1);

namespace NombaOne\Tests\Unit;

use NombaOne\Exceptions\WebhookVerificationException;
use NombaOne\Webhooks\WebhookEventType;
use NombaOne\Webhooks\Webhooks;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
final class WebhooksTest extends TestCase
{
    // The golden test vector — every NombaOne SDK must verify this byte-for-byte.
    private const GOLDEN_SECRET = 'nbo_whsec_golden_0123456789abcdef0123456789abcdef';
    private const GOLDEN_T = 1751600000;
    private const GOLDEN_PAYLOAD = '{"id":"nbo000000000001whd","type":"invoice.paid","event":{"id":"nbo000000000001evt","type":"invoice.paid","createdAt":"2026-07-04T10:00:00.000Z"},"data":{"reference":"nbo000000000001inv"}}';
    private const GOLDEN_HEADER = 't=1751600000,v1=ba56a072beccddbc014a3f72ef1b4a30e2008b61dcbcca4ae2f16c7e4427b374';

    private Webhooks $webhooks;

    protected function setUp(): void
    {
        // Keyless: the helper is usable without an API key.
        $this->webhooks = new Webhooks();
    }

    public function testGeneratesTheGoldenSignatureByteForByte(): void
    {
        $header = $this->webhooks->generateTestHeader(self::GOLDEN_PAYLOAD, self::GOLDEN_SECRET, self::GOLDEN_T);

        $this->assertSame(self::GOLDEN_HEADER, $header);
    }

    public function testConstructsTheGoldenEvent(): void
    {
        $event = $this->webhooks->constructEvent(
            self::GOLDEN_PAYLOAD,
            self::GOLDEN_HEADER,
            self::GOLDEN_SECRET,
            PHP_INT_MAX, // t is fixed in the past; ignore staleness for the golden vector
        );

        $this->assertSame('nbo000000000001whd', $event->id);
        $this->assertSame(WebhookEventType::INVOICE_PAID, $event->type);
        $this->assertSame('nbo000000000001evt', $event->event->id);
        $this->assertSame('nbo000000000001evt', $event->dedupeKey());
        $this->assertSame('nbo000000000001inv', $event->data['reference']);
    }

    public function testRejectsATamperedPayload(): void
    {
        $tampered = str_replace('nbo000000000001inv', 'nbo000000000009inv', self::GOLDEN_PAYLOAD);

        $this->expectException(WebhookVerificationException::class);
        $this->expectExceptionMessageMatches('/signature verification failed/');
        $this->webhooks->constructEvent($tampered, self::GOLDEN_HEADER, self::GOLDEN_SECRET, PHP_INT_MAX);
    }

    public function testRejectsTheWrongSecret(): void
    {
        $this->expectException(WebhookVerificationException::class);
        $this->expectExceptionMessageMatches('/signature verification failed/');
        $this->webhooks->constructEvent(self::GOLDEN_PAYLOAD, self::GOLDEN_HEADER, 'nbo_whsec_wrong', PHP_INT_MAX);
    }

    public function testRejectsAStaleTimestampAtDefaultTolerance(): void
    {
        $payload = '{"id":"nbo1whd","type":"invoice.paid","event":{"id":"nbo1evt","type":"invoice.paid","createdAt":"x"},"data":{}}';
        $header = $this->webhooks->generateTestHeader($payload, 'whsec_test', time() - 301);

        $this->expectException(WebhookVerificationException::class);
        $this->expectExceptionMessageMatches('/outside the allowed tolerance/');
        $this->webhooks->verifySignature($payload, $header, 'whsec_test');
    }

    public function testRejectsAFutureTimestampSymmetrically(): void
    {
        $payload = '{"id":"nbo1whd","type":"invoice.paid","event":{"id":"nbo1evt","type":"invoice.paid","createdAt":"x"},"data":{}}';
        $header = $this->webhooks->generateTestHeader($payload, 'whsec_test', time() + 301);

        $this->expectException(WebhookVerificationException::class);
        $this->expectExceptionMessageMatches('/outside the allowed tolerance/');
        $this->webhooks->verifySignature($payload, $header, 'whsec_test');
    }

    public function testAcceptsWhenOnlyTheSecondV1Matches(): void
    {
        // Secret rotation: multiple v1= pairs, only one valid.
        $valid = $this->webhooks->generateTestHeader(self::GOLDEN_PAYLOAD, self::GOLDEN_SECRET, self::GOLDEN_T);
        $realSignature = substr($valid, (int) strpos($valid, 'v1=') + 3);
        $header = 't=' . self::GOLDEN_T . ',v1=' . str_repeat('0', 64) . ',v1=' . $realSignature;

        $event = $this->webhooks->constructEvent(self::GOLDEN_PAYLOAD, $header, self::GOLDEN_SECRET, PHP_INT_MAX);

        $this->assertSame('nbo000000000001evt', $event->dedupeKey());
    }

    public function testMissingHeaderAndMissingSecretGiveDistinctMessages(): void
    {
        try {
            $this->webhooks->verifySignature(self::GOLDEN_PAYLOAD, '', self::GOLDEN_SECRET);
            $this->fail('Expected a WebhookVerificationException for a missing header.');
        } catch (WebhookVerificationException $e) {
            $this->assertStringContainsString('Missing X-Nombaone-Signature header', $e->getMessage());
        }

        try {
            $this->webhooks->verifySignature(self::GOLDEN_PAYLOAD, self::GOLDEN_HEADER, '');
            $this->fail('Expected a WebhookVerificationException for a missing secret.');
        } catch (WebhookVerificationException $e) {
            $this->assertStringContainsString('Missing signing secret', $e->getMessage());
        }
    }

    public function testRejectsAMalformedHeader(): void
    {
        $this->expectException(WebhookVerificationException::class);
        $this->expectExceptionMessageMatches('/Malformed X-Nombaone-Signature header/');
        $this->webhooks->verifySignature(self::GOLDEN_PAYLOAD, 't=123', self::GOLDEN_SECRET);
    }

    public function testRejectsANonUnixTimestamp(): void
    {
        $this->expectException(WebhookVerificationException::class);
        $this->expectExceptionMessageMatches('/`t` is not a unix timestamp/');
        $this->webhooks->verifySignature(self::GOLDEN_PAYLOAD, 't=notanumber,v1=abc', self::GOLDEN_SECRET);
    }

    public function testRejectsANonJsonBodyEvenWhenSigned(): void
    {
        $body = 'not json at all';
        $header = $this->webhooks->generateTestHeader($body, 'whsec_test');

        $this->expectException(WebhookVerificationException::class);
        $this->expectExceptionMessageMatches('/not valid JSON/');
        $this->webhooks->constructEvent($body, $header, 'whsec_test');
    }

    public function testRoundTripsAFreshlySignedDeliveryAtDefaultTolerance(): void
    {
        $payload = '{"id":"nbo2whd","type":"invoice.payment_failed","event":{"id":"nbo2evt","type":"invoice.payment_failed","createdAt":"x"},"data":{"reference":"nbo2inv","reason":"insufficient_funds"}}';
        $header = $this->webhooks->generateTestHeader($payload, 'whsec_test');

        $event = $this->webhooks->constructEvent($payload, $header, 'whsec_test');

        $this->assertSame(WebhookEventType::INVOICE_PAYMENT_FAILED, $event->type);
        $this->assertSame('insufficient_funds', $event->data['reason']);
    }

    public function testSynthesizesEventFromAFlatLegacyDeliveryShape(): void
    {
        // Older shape: id/type/createdAt at the top level, no nested `event`.
        $payload = '{"id":"nbo3evt","type":"customer.created","createdAt":"2026-07-05T10:00:00.000Z","data":{"reference":"nbo3cus"}}';
        $header = $this->webhooks->generateTestHeader($payload, 'whsec_test');

        $event = $this->webhooks->constructEvent($payload, $header, 'whsec_test');

        // Dedupe still works: the synthesized event id falls back to the top-level id.
        $this->assertSame('nbo3evt', $event->dedupeKey());
        $this->assertSame(WebhookEventType::CUSTOMER_CREATED, $event->type);
    }

    public function testEventTypeCatalogHasThirtyTwoPublicTypes(): void
    {
        $this->assertCount(32, WebhookEventType::all());
        $this->assertTrue(WebhookEventType::isKnown(WebhookEventType::SUBSCRIPTION_CHURNED));
        $this->assertFalse(WebhookEventType::isKnown('some.future.event'));
    }
}
