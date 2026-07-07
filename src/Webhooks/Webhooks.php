<?php

declare(strict_types=1);

namespace NombaOne\Webhooks;

use NombaOne\Exceptions\WebhookVerificationException;

/**
 * Verify and parse NombaOne webhook deliveries.
 *
 * Available as `$nomba->webhooks` on a client, or standalone via
 * `new NombaOne\Webhooks\Webhooks()` — verification needs only the endpoint's
 * signing secret, never an API key.
 *
 * **Feed it the raw request body.** Decoding and re-encoding JSON can reorder
 * keys and change bytes, which breaks the signature. Capture the body before
 * any framework parses it (e.g. `file_get_contents('php://input')`, or the raw
 * body from your framework's request object).
 */
final class Webhooks
{
    /** Default allowed age (seconds) between the delivery timestamp and now, either direction. */
    public const DEFAULT_TOLERANCE = 300;

    /**
     * Verify a delivery's signature and timestamp, then parse and return the
     * event. This is the one call your handler needs.
     *
     * Delivery is **at-least-once** — after verification, dedupe on
     * `$event->dedupeKey()` before acting.
     *
     * @param string $payload         the exact raw request body
     * @param string $signatureHeader the `X-Nombaone-Signature` header value
     * @param string $secret          the endpoint's signing secret (shown once at creation)
     * @param int    $tolerance       max allowed timestamp age in seconds (default 300)
     *
     * @throws WebhookVerificationException on a missing/malformed header, a stale
     *                                      timestamp, an invalid signature, or a non-JSON body
     *
     * @example
     * ```php
     * $event = $nomba->webhooks->constructEvent(
     *     file_get_contents('php://input'),
     *     $_SERVER['HTTP_X_NOMBAONE_SIGNATURE'] ?? '',
     *     getenv('NOMBAONE_WEBHOOK_SECRET'),
     * );
     * if ($store->alreadyProcessed($event->dedupeKey())) {
     *     http_response_code(200);
     *     return;
     * }
     * if ($event->type === \NombaOne\Webhooks\WebhookEventType::INVOICE_PAID) {
     *     unlockAccess($event->data['reference']);
     * }
     * http_response_code(200); // respond 2xx fast; do heavy work async
     * ```
     */
    public function constructEvent(
        string $payload,
        string $signatureHeader,
        string $secret,
        int $tolerance = self::DEFAULT_TOLERANCE,
    ): WebhookEvent {
        $this->verifySignature($payload, $signatureHeader, $secret, $tolerance);

        try {
            /** @var mixed $parsed */
            $parsed = json_decode($payload, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw new WebhookVerificationException('Webhook payload was not valid JSON.');
        }
        if (!is_array($parsed)) {
            throw new WebhookVerificationException('Webhook payload was not a JSON object.');
        }

        return WebhookEvent::fromArray($parsed);
    }

    /**
     * Verify only (no parse). Throws a {@see WebhookVerificationException} with a
     * distinct message per failure mode; returns nothing on success.
     *
     * @throws WebhookVerificationException
     */
    public function verifySignature(
        string $payload,
        string $signatureHeader,
        string $secret,
        int $tolerance = self::DEFAULT_TOLERANCE,
    ): void {
        if ($signatureHeader === '') {
            throw new WebhookVerificationException(
                'Missing X-Nombaone-Signature header — is this request really from NombaOne?',
            );
        }
        if ($secret === '') {
            throw new WebhookVerificationException(
                'Missing signing secret — pass the secret shown when the endpoint was created.',
            );
        }

        [$timestamp, $signatures] = $this->parseHeader($signatureHeader);

        if (!is_numeric($timestamp)) {
            throw new WebhookVerificationException(
                'Malformed X-Nombaone-Signature header — `t` is not a unix timestamp.',
            );
        }

        $age = abs(time() - (int) $timestamp);
        if ($age > $tolerance) {
            throw new WebhookVerificationException(
                "Webhook timestamp is outside the allowed tolerance ({$age}s old, limit {$tolerance}s) — possible replay, or severe clock skew.",
            );
        }

        $expected = $this->computeSignature($secret, $timestamp, $payload);
        foreach ($signatures as $candidate) {
            // Multiple `v1` entries are legal during secret rotation — any match passes.
            if (hash_equals($expected, $candidate)) {
                return;
            }
        }

        throw new WebhookVerificationException(
            "Webhook signature verification failed — check you are using this endpoint's current signing secret "
            . 'and the exact raw request body (no re-serialization).',
        );
    }

    /**
     * Build a valid `X-Nombaone-Signature` header for a payload — for testing
     * your own handler without waiting on a real delivery.
     *
     * @example
     * ```php
     * $header = $nomba->webhooks->generateTestHeader($payload, 'whsec_test');
     * $event = $nomba->webhooks->constructEvent($payload, $header, 'whsec_test');
     * ```
     */
    public function generateTestHeader(string $payload, string $secret, ?int $timestamp = null): string
    {
        $t = (string) ($timestamp ?? time());

        return "t={$t},v1=" . $this->computeSignature($secret, $t, $payload);
    }

    private function computeSignature(string $secret, string $timestamp, string $payload): string
    {
        return hash_hmac('sha256', "{$timestamp}.{$payload}", $secret);
    }

    /**
     * @return array{0: string, 1: list<string>}
     */
    private function parseHeader(string $header): array
    {
        $timestamp = null;
        $signatures = [];
        foreach (explode(',', $header) as $pair) {
            $equals = strpos($pair, '=');
            if ($equals === false) {
                continue;
            }
            $key = trim(substr($pair, 0, $equals));
            $value = trim(substr($pair, $equals + 1));
            if ($key === 't') {
                $timestamp = $value;
            }
            if ($key === 'v1' && $value !== '') {
                $signatures[] = $value;
            }
        }

        if ($timestamp === null || $signatures === []) {
            throw new WebhookVerificationException(
                'Malformed X-Nombaone-Signature header — expected "t=<unix>,v1=<hex>".',
            );
        }

        return [$timestamp, $signatures];
    }
}
