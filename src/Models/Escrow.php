<?php

declare(strict_types=1);

namespace NombaOne\Models;

/** Your escrow lock and what is actually withdrawable right now (integer kobo). */
final class Escrow extends Model
{
    public function __construct(
        public readonly string $domain,
        public readonly int $lockedInKobo,
        public readonly string $since,
        public readonly int $balanceInKobo,
        public readonly int $minWithdrawableInKobo,
        public readonly int $availableInKobo,
        array $raw = [],
    ) {
        parent::__construct($raw);
    }

    public static function fromArray(array $data): static
    {
        return new self(
            domain: Field::str($data, 'domain', 'escrow'),
            lockedInKobo: Field::int($data, 'lockedInKobo'),
            since: Field::str($data, 'since'),
            balanceInKobo: Field::int($data, 'balanceInKobo'),
            minWithdrawableInKobo: Field::int($data, 'minWithdrawableInKobo'),
            availableInKobo: Field::int($data, 'availableInKobo'),
            raw: $data,
        );
    }
}
