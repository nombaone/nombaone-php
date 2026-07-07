<?php

declare(strict_types=1);

namespace NombaOne\Http;

use NombaOne\Exceptions\ConnectionException;
use NombaOne\Exceptions\TimeoutException;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;

/**
 * The default HTTP client, built on ext-curl. It honors a real per-attempt
 * timeout (which pure PSR-18 cannot express) and depends only on curl plus a
 * PSR-17 factory to shape the response.
 *
 * If ext-curl is unavailable, construct the client with a {@see Psr18HttpClient}
 * instead.
 */
final class CurlHttpClient implements HttpClient
{
    public function __construct(
        private readonly ResponseFactoryInterface $responseFactory,
        private readonly StreamFactoryInterface $streamFactory,
    ) {
        if (!\function_exists('curl_init')) {
            throw new ConnectionException(
                'The cURL extension is not available. Install ext-curl, or pass a PSR-18 client via the `httpClient` option (see NombaOne\\Http\\Psr18HttpClient).',
            );
        }
    }

    public function send(RequestInterface $request, float $timeout): ResponseInterface
    {
        $handle = curl_init();
        if ($handle === false) {
            throw new ConnectionException('Failed to initialize a cURL handle.');
        }

        /** @var array<string, list<string>> $responseHeaders */
        $responseHeaders = [];

        $options = [
            CURLOPT_CUSTOMREQUEST => $request->getMethod(),
            CURLOPT_URL => (string) $request->getUri(),
            CURLOPT_HTTPHEADER => $this->flattenHeaders($request),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => false,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_HEADERFUNCTION => static function ($_handle, string $line) use (&$responseHeaders): int {
                $length = \strlen($line);
                $trimmed = trim($line);
                if ($trimmed === '' || stripos($trimmed, 'HTTP/') === 0) {
                    return $length;
                }
                $colon = strpos($trimmed, ':');
                if ($colon !== false) {
                    $name = trim(substr($trimmed, 0, $colon));
                    $value = trim(substr($trimmed, $colon + 1));
                    $responseHeaders[$name][] = $value;
                }

                return $length;
            },
        ];

        $body = (string) $request->getBody();
        if ($body !== '') {
            $options[CURLOPT_POSTFIELDS] = $body;
        }
        if ($timeout > 0) {
            $milliseconds = (int) ceil($timeout * 1000);
            $options[CURLOPT_TIMEOUT_MS] = $milliseconds;
            $options[CURLOPT_CONNECTTIMEOUT_MS] = $milliseconds;
        }

        curl_setopt_array($handle, $options);

        $result = curl_exec($handle);
        $errno = curl_errno($handle);
        $errorMessage = curl_error($handle);
        $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        // The handle is freed when it goes out of scope; curl_close() is a no-op
        // since PHP 8.0 and deprecated in 8.5.

        if ($errno !== 0) {
            if ($errno === CURLE_OPERATION_TIMEDOUT) {
                throw new TimeoutException("Request timed out after {$timeout}s.");
            }
            throw new ConnectionException("Request failed to reach the NombaOne API: {$errorMessage}");
        }

        $response = $this->responseFactory->createResponse($status);
        foreach ($responseHeaders as $name => $values) {
            $response = $response->withHeader($name, $values);
        }

        return $response->withBody(
            $this->streamFactory->createStream(\is_string($result) ? $result : ''),
        );
    }

    /**
     * @return list<string>
     */
    private function flattenHeaders(RequestInterface $request): array
    {
        $flat = [];
        foreach ($request->getHeaders() as $name => $values) {
            foreach ($values as $value) {
                $flat[] = "{$name}: {$value}";
            }
        }

        return $flat;
    }
}
