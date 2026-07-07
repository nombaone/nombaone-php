<?php

declare(strict_types=1);

namespace NombaOne\Http;

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * The transport seam. The SDK sends every request through an implementation of
 * this interface, so you can swap in your own HTTP stack — and tests can inject
 * a recording double.
 *
 * The default is {@see CurlHttpClient} (honors the per-attempt timeout). To
 * reuse an existing PSR-18 client instead, wrap it in {@see Psr18HttpClient}.
 */
interface HttpClient
{
    /**
     * Send one request and return the response.
     *
     * @param float $timeout per-attempt timeout in seconds (`0` means no timeout)
     *
     * @throws \NombaOne\Exceptions\ConnectionException on a transport failure
     * @throws \NombaOne\Exceptions\TimeoutException     when the attempt exceeds $timeout
     */
    public function send(RequestInterface $request, float $timeout): ResponseInterface;
}
