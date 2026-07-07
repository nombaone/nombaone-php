<?php

declare(strict_types=1);

namespace NombaOne;

use Http\Discovery\Psr17FactoryDiscovery;
use Http\Discovery\Psr18ClientDiscovery;
use NombaOne\Enums\Mode;
use NombaOne\Exceptions\ConnectionException;
use NombaOne\Exceptions\NombaOneException;
use NombaOne\Http\ApiResponse;
use NombaOne\Http\CurlHttpClient;
use NombaOne\Http\HttpClient;
use NombaOne\Http\Psr18HttpClient;
use NombaOne\Http\Request;
use NombaOne\Http\Transport;
use NombaOne\Http\TransportConfig;
use NombaOne\Resources\Coupons;
use NombaOne\Resources\Customers;
use NombaOne\Resources\Events;
use NombaOne\Resources\Invoices;
use NombaOne\Resources\Mandates;
use NombaOne\Resources\Metrics;
use NombaOne\Resources\Organization;
use NombaOne\Resources\PaymentMethods;
use NombaOne\Resources\Plans;
use NombaOne\Resources\Prices;
use NombaOne\Resources\Sandbox;
use NombaOne\Resources\Settlements;
use NombaOne\Resources\Subscriptions;
use NombaOne\Resources\WebhookEndpoints;
use NombaOne\Webhooks\Webhooks;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;

/**
 * The NombaOne API client.
 *
 * The environment is derived from your key prefix: `nbo_sandbox_…` talks to the
 * sandbox host, `nbo_live_…` to the live host. Keys are server-side secrets —
 * never ship one to a browser or mobile app.
 *
 * @example
 * ```php
 * use NombaOne\Nombaone;
 *
 * $nomba = new Nombaone(getenv('NOMBAONE_API_KEY'));
 *
 * $subscription = $nomba->subscriptions->create([
 *     'customerId' => 'nbo123456789012cus',
 *     'priceId'    => 'nbo123456789012prc',
 * ]);
 * ```
 */
final class Nombaone
{
    /** Default host for each environment. Overridable via the `baseUrl` option. */
    public const BASE_URLS = [
        'sandbox' => 'https://sandbox.api.nombaone.xyz',
        'live' => 'https://api.nombaone.xyz',
    ];

    /** Per-attempt timeout, in seconds. */
    private const DEFAULT_TIMEOUT = 30.0;

    /** Automatic retry budget (total attempts = maxRetries + 1). */
    private const DEFAULT_MAX_RETRIES = 2;

    /** The environment this client talks to, derived from the key prefix. */
    public readonly Mode $mode;

    /** The API origin in use (no `/v1`). */
    public readonly string $baseUrl;

    private readonly Transport $transport;

    // --- Resource namespaces ---

    /** Customers — the people and businesses you bill. */
    public readonly Customers $customers;

    /** Plans — your catalog (prices nest under `$nomba->plans->prices`). */
    public readonly Plans $plans;

    /** Prices — immutable amounts and cadences. */
    public readonly Prices $prices;

    /** Subscriptions — the core billing object. */
    public readonly Subscriptions $subscriptions;

    /** Invoices — what billing cycles produced (read + void). */
    public readonly Invoices $invoices;

    /** Coupons — reusable discount rules. */
    public readonly Coupons $coupons;

    /** Payment methods — cards, mandates, virtual accounts. */
    public readonly PaymentMethods $paymentMethods;

    /** Direct-debit mandates (async NIBSS consent). */
    public readonly Mandates $mandates;

    /** Settlements, refunds, payouts, escrow. */
    public readonly Settlements $settlements;

    /** Webhook endpoint management (REST). To verify deliveries, see `$nomba->webhooks`. */
    public readonly WebhookEndpoints $webhookEndpoints;

    /** The domain-event log — your reconciliation backstop. */
    public readonly Events $events;

    /** Organization settings + billing/dunning policy. */
    public readonly Organization $organization;

    /** Billing KPIs computed from the ledger. */
    public readonly Metrics $metrics;

    /** Sandbox-only simulation instruments (test clock, test methods, webhook simulate). */
    public readonly Sandbox $sandbox;

    /** Verify + parse incoming webhook deliveries (crypto helper, no API calls). */
    public readonly Webhooks $webhooks;

    /**
     * @param string|array<string, mixed>|null $apiKey your API key, or an options array
     * @param array<string, mixed>             $options
     *
     * Recognized options: `apiKey`, `baseUrl`, `timeout` (seconds, default 30),
     * `maxRetries` (default 2), `defaultHeaders`, `httpClient`
     * ({@see HttpClient} or a PSR-18 client), `requestFactory`, `streamFactory`,
     * `responseFactory` (PSR-17; auto-discovered when omitted).
     *
     * @throws NombaOneException when the key is missing or its prefix is unrecognized
     */
    public function __construct(string|array|null $apiKey = null, array $options = [])
    {
        if (is_array($apiKey)) {
            $options = $apiKey;
        } elseif ($apiKey !== null) {
            $options['apiKey'] = $apiKey;
        }

        $apiKey = $this->resolveApiKey($options);

        $mode = self::deriveMode($apiKey);
        $baseUrlOption = $options['baseUrl'] ?? null;
        if ($mode === null && !is_string($baseUrlOption)) {
            throw new NombaOneException(
                'Unrecognized API key format — expected a key starting with "nbo_sandbox_" or "nbo_live_". '
                . 'Copy the key exactly as shown in the dashboard, or pass an explicit baseUrl if you are targeting a custom host.',
            );
        }
        $this->mode = $mode ?? Mode::Sandbox;

        $baseUrl = is_string($baseUrlOption) && $baseUrlOption !== ''
            ? $baseUrlOption
            : self::BASE_URLS[$this->mode->value];
        $this->baseUrl = rtrim($baseUrl, '/');

        $requestFactory = $this->resolveRequestFactory($options['requestFactory'] ?? null);
        $streamFactory = $this->resolveStreamFactory($options['streamFactory'] ?? null);
        $httpClient = $this->resolveHttpClient($options, $streamFactory);

        $sleeper = $options['sleeper'] ?? null;

        $config = new TransportConfig(
            apiKey: $apiKey,
            baseUrl: $this->baseUrl,
            timeout: $this->resolveTimeout($options),
            maxRetries: $this->resolveMaxRetries($options),
            defaultHeaders: $this->resolveDefaultHeaders($options),
            sleeper: $sleeper instanceof \Closure ? $sleeper : null,
        );

        $this->transport = new Transport($config, $httpClient, $requestFactory, $streamFactory);

        $this->customers = new Customers($this);
        $this->plans = new Plans($this);
        $this->prices = new Prices($this);
        $this->subscriptions = new Subscriptions($this);
        $this->invoices = new Invoices($this);
        $this->coupons = new Coupons($this);
        $this->paymentMethods = new PaymentMethods($this);
        $this->mandates = new Mandates($this);
        $this->settlements = new Settlements($this);
        $this->webhookEndpoints = new WebhookEndpoints($this);
        $this->events = new Events($this);
        $this->organization = new Organization($this);
        $this->metrics = new Metrics($this);
        $this->sandbox = new Sandbox($this);
        $this->webhooks = new Webhooks();
    }

    /**
     * Send one request through the transport and return the full response.
     *
     * @internal Used by resource classes.
     */
    public function send(Request $request): ApiResponse
    {
        return $this->transport->send($request);
    }

    private static function deriveMode(string $apiKey): ?Mode
    {
        if (str_starts_with($apiKey, 'nbo_sandbox_')) {
            return Mode::Sandbox;
        }
        if (str_starts_with($apiKey, 'nbo_live_')) {
            return Mode::Live;
        }

        return null;
    }

    /**
     * @param array<string, mixed> $options
     */
    private function resolveApiKey(array $options): string
    {
        $apiKey = $options['apiKey'] ?? null;
        if (!is_string($apiKey) || $apiKey === '') {
            $fromEnv = getenv('NOMBAONE_API_KEY');
            $apiKey = $fromEnv !== false && $fromEnv !== '' ? $fromEnv : null;
        }
        if (!is_string($apiKey)) {
            throw new NombaOneException(
                'Missing API key — set the NOMBAONE_API_KEY environment variable, or pass one: '
                . 'new Nombaone("nbo_sandbox_…"). Create keys in the dashboard under API keys.',
            );
        }

        return $apiKey;
    }

    /**
     * @param array<string, mixed> $options
     */
    private function resolveTimeout(array $options): float
    {
        $timeout = $options['timeout'] ?? null;

        return is_int($timeout) || is_float($timeout) ? (float) $timeout : self::DEFAULT_TIMEOUT;
    }

    /**
     * @param array<string, mixed> $options
     */
    private function resolveMaxRetries(array $options): int
    {
        $maxRetries = $options['maxRetries'] ?? null;

        return is_int($maxRetries) ? max(0, $maxRetries) : self::DEFAULT_MAX_RETRIES;
    }

    /**
     * @param array<string, mixed> $options
     *
     * @return array<string, string|null>|null
     */
    private function resolveDefaultHeaders(array $options): ?array
    {
        $headers = $options['defaultHeaders'] ?? null;
        if (!is_array($headers)) {
            return null;
        }
        $out = [];
        foreach ($headers as $name => $value) {
            if ($value === null || is_string($value)) {
                $out[(string) $name] = $value;
            }
        }

        return $out;
    }

    private function resolveRequestFactory(mixed $factory): RequestFactoryInterface
    {
        if ($factory instanceof RequestFactoryInterface) {
            return $factory;
        }

        return $this->discover(
            static fn (): RequestFactoryInterface => Psr17FactoryDiscovery::findRequestFactory(),
            'PSR-17 request factory',
        );
    }

    private function resolveStreamFactory(mixed $factory): StreamFactoryInterface
    {
        if ($factory instanceof StreamFactoryInterface) {
            return $factory;
        }

        return $this->discover(
            static fn (): StreamFactoryInterface => Psr17FactoryDiscovery::findStreamFactory(),
            'PSR-17 stream factory',
        );
    }

    /**
     * @param array<string, mixed> $options
     */
    private function resolveHttpClient(array $options, StreamFactoryInterface $streamFactory): HttpClient
    {
        $client = $options['httpClient'] ?? null;

        if ($client instanceof HttpClient) {
            return $client;
        }
        if ($client instanceof ClientInterface) {
            return new Psr18HttpClient($client);
        }

        $responseFactory = $options['responseFactory'] ?? null;
        $responseFactory = $responseFactory instanceof ResponseFactoryInterface
            ? $responseFactory
            : $this->discover(
                static fn (): ResponseFactoryInterface => Psr17FactoryDiscovery::findResponseFactory(),
                'PSR-17 response factory',
            );

        try {
            return new CurlHttpClient($responseFactory, $streamFactory);
        } catch (ConnectionException) {
            // No cURL — fall back to a discovered PSR-18 client.
            return new Psr18HttpClient($this->discover(
                static fn (): ClientInterface => Psr18ClientDiscovery::find(),
                'PSR-18 HTTP client',
            ));
        }
    }

    /**
     * Run a php-http/discovery lookup, turning its cryptic failure into an
     * actionable {@see NombaOneException} telling the developer exactly what to
     * install.
     *
     * @template T of object
     *
     * @param callable(): T $discover
     *
     * @return T
     */
    private function discover(callable $discover, string $what): object
    {
        try {
            return $discover();
        } catch (\Http\Discovery\Exception\NotFoundException | \Http\Discovery\Exception\DiscoveryFailedException $e) {
            throw new NombaOneException(
                "No {$what} could be auto-discovered. The NombaOne SDK needs a PSR-17/PSR-18 HTTP "
                . 'stack — install one (e.g. `composer require guzzlehttp/guzzle`), or pass your own '
                . 'via the httpClient / requestFactory / streamFactory options.',
                0,
                $e,
            );
        }
    }
}
