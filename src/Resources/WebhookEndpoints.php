<?php

declare(strict_types=1);

namespace NombaOne\Resources;

use NombaOne\Http\Request;
use NombaOne\Models\RotatedWebhookSecret;
use NombaOne\Models\WebhookEndpoint;
use NombaOne\Models\WebhookEndpointWithSecret;
use NombaOne\Nombaone;
use NombaOne\Page;

/**
 * Webhook endpoints — register and manage the URLs that receive signed events.
 * To *verify* incoming deliveries in your handler, use `$nomba->webhooks` (the
 * crypto helper), not this REST resource.
 */
final class WebhookEndpoints extends Resource
{
    /** Deliveries under an endpoint. */
    public readonly WebhookEndpointDeliveries $deliveries;

    public function __construct(Nombaone $client)
    {
        parent::__construct($client);
        $this->deliveries = new WebhookEndpointDeliveries($client);
    }

    /**
     * Register an endpoint. The response includes the full `signingSecret`
     * **exactly once** — store it in your secret manager immediately.
     *
     * @param array{url: string, enabledEvents?: list<string>} $params
     * @param array<string, mixed>                             $options
     *
     * @example
     * ```php
     * $endpoint = $nomba->webhookEndpoints->create([
     *     'url'           => 'https://example.com/nombaone/webhooks',
     *     'enabledEvents' => ['invoice.paid', 'invoice.payment_failed'],
     * ]);
     * $secrets->store('NOMBAONE_WEBHOOK_SECRET', $endpoint->signingSecret);
     * ```
     */
    public function create(array $params, array $options = []): WebhookEndpointWithSecret
    {
        return $this->requestModel(WebhookEndpointWithSecret::class, new Request(
            'POST',
            '/webhooks',
            body: $params,
            options: self::opts($options),
        ));
    }

    /**
     * Retrieve an endpoint by id.
     *
     * @param array<string, mixed> $options
     */
    public function retrieve(string $id, array $options = []): WebhookEndpoint
    {
        return $this->requestModel(WebhookEndpoint::class, new Request(
            'GET',
            '/webhooks/' . self::seg($id),
            options: self::opts($options),
        ));
    }

    /**
     * Update url, event subscription, or enabled state. At least one field.
     *
     * @param array{url?: string, enabledEvents?: list<string>, disabled?: bool} $params
     * @param array<string, mixed>                                               $options
     */
    public function update(string $id, array $params, array $options = []): WebhookEndpoint
    {
        return $this->requestModel(WebhookEndpoint::class, new Request(
            'PATCH',
            '/webhooks/' . self::seg($id),
            body: $params,
            options: self::opts($options),
        ));
    }

    /**
     * List your endpoints, newest first.
     *
     * @param array{limit?: int, cursor?: string} $params
     * @param array<string, mixed>                $options
     *
     * @return Page<WebhookEndpoint>
     */
    public function list(array $params = [], array $options = []): Page
    {
        return $this->requestPage(WebhookEndpoint::class, new Request(
            'GET',
            '/webhooks',
            query: $params,
            options: self::opts($options),
        ));
    }

    /**
     * Delete an endpoint. Pending deliveries to it are retired.
     *
     * @param array<string, mixed> $options
     */
    public function delete(string $id, array $options = []): WebhookEndpoint
    {
        return $this->requestModel(WebhookEndpoint::class, new Request(
            'DELETE',
            '/webhooks/' . self::seg($id),
            options: self::opts($options),
        ));
    }

    /**
     * Rotate the signing secret. The new secret is returned **exactly once**;
     * the old one is briefly honored so you can roll without dropping in-flight
     * deliveries.
     *
     * @param array<string, mixed> $options
     */
    public function rotateSecret(string $id, array $options = []): RotatedWebhookSecret
    {
        return $this->requestModel(RotatedWebhookSecret::class, new Request(
            'POST',
            '/webhooks/' . self::seg($id) . '/rotate-secret',
            body: [],
            options: self::opts($options),
        ));
    }
}
