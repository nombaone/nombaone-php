<?php

declare(strict_types=1);

namespace NombaOne\Http;

/**
 * The cursor-pagination block returned at the top level of every list response.
 * Pagination is cursor-only and forward-only — there are no total counts.
 */
final class Pagination
{
    public function __construct(
        /** The page size that was applied (1–100; the API default is 20). */
        public readonly int $limit,
        /** Whether more items exist beyond this page. */
        public readonly bool $hasMore,
        /** Opaque cursor for the next page, or `null` when `hasMore` is false. */
        public readonly ?string $nextCursor,
    ) {
    }

    /**
     * @param array<array-key, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $limit = $data['limit'] ?? 0;
        $nextCursor = $data['nextCursor'] ?? null;

        return new self(
            limit: is_int($limit) ? $limit : (is_numeric($limit) ? (int) $limit : 0),
            hasMore: (bool) ($data['hasMore'] ?? false),
            nextCursor: is_string($nextCursor) ? $nextCursor : null,
        );
    }
}
