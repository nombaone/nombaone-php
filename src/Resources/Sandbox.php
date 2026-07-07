<?php

declare(strict_types=1);

namespace NombaOne\Resources;

use NombaOne\Enums\Mode;
use NombaOne\Exceptions\NombaOneException;
use NombaOne\Http\Request;
use NombaOne\Models\AdvanceCycleResult;
use NombaOne\Models\PaymentMethod;
use NombaOne\Models\WebhookSimulation;

/**
 * **Sandbox only.** Simulation instruments that make billing outcomes happen on
 * demand — no cron waits, no real cards. These endpoints exist only on the
 * sandbox deployment; calling them with a live key throws locally, before any
 * network request.
 */
final class Sandbox extends Resource
{
    /**
     * **Sandbox only.** Mint a ready, chargeable test payment method whose
     * `behavior` decides every future charge outcome deterministically.
     *
     * @param array{customerId: string, behavior?: string, kind?: string} $params behavior: `success` | `decline_insufficient_funds` | `decline_expired_card` | `decline_do_not_honor` | `requires_otp`; kind: `card` | `mandate`
     * @param array<string, mixed> $options
     *
     * @throws NombaOneException when the client was constructed with a live key
     *
     * @example
     * ```php
     * $method = $nomba->sandbox->createPaymentMethod([
     *     'customerId' => $customer->id,
     *     'behavior'   => 'decline_insufficient_funds', // rehearse thin-balance dunning
     * ]);
     * ```
     */
    public function createPaymentMethod(array $params, array $options = []): PaymentMethod
    {
        $this->assertSandbox();

        return $this->requestModel(PaymentMethod::class, new Request(
            'POST',
            '/sandbox/payment-methods',
            body: $params,
            options: self::opts($options),
        ));
    }

    /**
     * **Sandbox only.** The test clock: run the subscription's next billing
     * cycle right now, through the real engine — invoice, charge, ledger,
     * webhooks and all.
     *
     * @param array<string, mixed> $options
     *
     * @throws NombaOneException when the client was constructed with a live key
     *
     * @example
     * ```php
     * $result = $nomba->sandbox->advanceCycle($subscription->id);
     * echo $result->outcome;                    // "paid"
     * echo $result->invoice->totalInKobo;       // the real invoice it produced
     * ```
     */
    public function advanceCycle(string $subscriptionId, array $options = []): AdvanceCycleResult
    {
        $this->assertSandbox();

        return $this->requestModel(AdvanceCycleResult::class, new Request(
            'POST',
            '/sandbox/subscriptions/' . self::seg($subscriptionId) . '/advance-cycle',
            body: [],
            options: self::opts($options),
        ));
    }

    /**
     * **Sandbox only.** Emit a real, signed catalog event to your registered
     * endpoints — the genuine pipeline (real secret, real signature, real
     * retries), not a mock. The sandbox sends no organic webhooks; this is how
     * you rehearse your handler.
     *
     * @param array{type: string, payload?: array<string, mixed>} $params
     * @param array<string, mixed>                                $options
     *
     * @throws NombaOneException when the client was constructed with a live key
     *
     * @example
     * ```php
     * $nomba->sandbox->simulateWebhook([
     *     'type'    => 'invoice.payment_failed',
     *     'payload' => ['reference' => $invoice->id, 'reason' => 'insufficient_funds'],
     * ]);
     * ```
     */
    public function simulateWebhook(array $params, array $options = []): WebhookSimulation
    {
        $this->assertSandbox();

        return $this->requestModel(WebhookSimulation::class, new Request(
            'POST',
            '/sandbox/webhooks/simulate',
            body: $params,
            options: self::opts($options),
        ));
    }

    private function assertSandbox(): void
    {
        if ($this->client->mode === Mode::Live) {
            throw new NombaOneException(
                '$nomba->sandbox->* only works with a sandbox key (nbo_sandbox_…) — the /v1/sandbox endpoints '
                . 'do not exist on the live API. Use your sandbox key to rehearse, then go live without the sandbox calls.',
            );
        }
    }
}
