<?php

declare(strict_types=1);

namespace NombaOne\Exceptions;

use NombaOne\ErrorCode;
use Psr\Http\Message\ResponseInterface;

/**
 * A non-2xx response from the API. Carries everything the error envelope said:
 * the stable {@see $errorCode} to branch on, the {@see $hint} telling you how to
 * fix it, the {@see $docUrl} into the error reference, per-field validation
 * errors on 422s, and the {@see $requestId} to quote to support.
 *
 * The {@see $hint} is folded into `getMessage()` so the fix arrives with the
 * failure — you never need a docs tab to understand what went wrong.
 *
 * (The machine code is `$errorCode`, not `$code`, because PHP's `\Exception`
 * reserves `$code` for its own numeric code. Compare against {@see \NombaOne\ErrorCode}.)
 *
 * Subclasses are keyed by HTTP status so `instanceof` reads naturally:
 * {@see AuthenticationException}, {@see RateLimitException},
 * {@see ValidationException}, and so on.
 */
class ApiException extends NombaOneException
{
    /**
     * @param array<string, list<string>>|null $fields Per-field validation errors, present on 422s.
     */
    public function __construct(
        string $message,
        public readonly int $statusCode,
        public readonly string $errorCode,
        public readonly string $hint = '',
        public readonly string $docUrl = '',
        public readonly ?array $fields = null,
        public readonly ?string $requestId = null,
        ?\Throwable $previous = null,
    ) {
        // Surface the hint in the thrown message itself — the fix should arrive
        // with the failure, without a docs tab.
        parent::__construct($hint !== '' ? "{$message} — {$hint}" : $message, 0, $previous);
    }

    /**
     * Build the right {@see ApiException} subclass from a status code, the
     * decoded response body (or null for a non-JSON body), and the raw response.
     *
     * @param array<mixed>|null $body
     */
    public static function fromResponse(int $status, ?array $body, ResponseInterface $response): self
    {
        $error = [];
        if ($body !== null && isset($body['error']) && is_array($body['error'])) {
            $error = $body['error'];
        }

        $rawCode = $error['code'] ?? null;
        $code = is_string($rawCode) && $rawCode !== '' ? $rawCode : self::defaultCodeForStatus($status);

        $rawMessage = $error['message'] ?? null;
        $message = is_string($rawMessage) && $rawMessage !== '' ? $rawMessage : "Request failed with status {$status}";

        $hint = is_string($error['hint'] ?? null) ? $error['hint'] : '';
        $docUrl = is_string($error['docUrl'] ?? null) ? $error['docUrl'] : '';
        $fields = self::extractFields($error['fields'] ?? null);
        $requestId = self::extractRequestId($body, $response);

        if ($status === 429) {
            return new RateLimitException(
                $message,
                $status,
                $code,
                $hint,
                $docUrl,
                $fields,
                $requestId,
                self::intHeader($response, 'Retry-After'),
                self::intHeader($response, 'X-RateLimit-Limit'),
                self::intHeader($response, 'X-RateLimit-Remaining'),
            );
        }

        return match (true) {
            $status === 400 => new BadRequestException($message, $status, $code, $hint, $docUrl, $fields, $requestId),
            $status === 401 => new AuthenticationException($message, $status, $code, $hint, $docUrl, $fields, $requestId),
            $status === 403 => new PermissionDeniedException($message, $status, $code, $hint, $docUrl, $fields, $requestId),
            $status === 404 => new NotFoundException($message, $status, $code, $hint, $docUrl, $fields, $requestId),
            $status === 409 => new ConflictException($message, $status, $code, $hint, $docUrl, $fields, $requestId),
            $status === 422 => new ValidationException($message, $status, $code, $hint, $docUrl, $fields, $requestId),
            $status >= 500 => new ServerException($message, $status, $code, $hint, $docUrl, $fields, $requestId),
            default => new self($message, $status, $code, $hint, $docUrl, $fields, $requestId),
        };
    }

    /** The fallback code for a status when the body carries no usable `error.code`. */
    public static function defaultCodeForStatus(int $status): string
    {
        return match ($status) {
            400 => ErrorCode::CLIENT_INVALID_REQUEST,
            401 => ErrorCode::API_KEY_INVALID,
            403 => ErrorCode::CLIENT_FORBIDDEN,
            404 => ErrorCode::CLIENT_RESOURCE_NOT_FOUND,
            409 => ErrorCode::CLIENT_CONFLICT,
            422 => ErrorCode::CLIENT_VALIDATION_FAILED,
            429 => ErrorCode::RATE_LIMIT_EXCEEDED,
            502, 503, 504 => ErrorCode::SYSTEM_UPSTREAM_ERROR,
            default => ErrorCode::SYSTEM_INTERNAL_ERROR,
        };
    }

    /**
     * @return array<string, list<string>>|null
     */
    private static function extractFields(mixed $raw): ?array
    {
        if (!is_array($raw)) {
            return null;
        }
        $fields = [];
        foreach ($raw as $key => $messages) {
            if (!is_array($messages)) {
                continue;
            }
            $list = [];
            foreach ($messages as $message) {
                if (is_string($message)) {
                    $list[] = $message;
                }
            }
            $fields[(string) $key] = $list;
        }

        return $fields;
    }

    /**
     * @param array<mixed>|null $body
     */
    private static function extractRequestId(?array $body, ResponseInterface $response): ?string
    {
        if ($body !== null && isset($body['meta']) && is_array($body['meta'])) {
            $requestId = $body['meta']['requestId'] ?? null;
            if (is_string($requestId) && $requestId !== '') {
                return $requestId;
            }
        }
        $header = $response->getHeaderLine('X-Request-Id');

        return $header !== '' ? $header : null;
    }

    private static function intHeader(ResponseInterface $response, string $name): ?int
    {
        $value = $response->getHeaderLine($name);

        return is_numeric($value) ? (int) $value : null;
    }
}
