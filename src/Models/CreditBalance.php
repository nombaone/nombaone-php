<?php

declare(strict_types=1);

namespace NombaOne\Models;

/**
 * A customer's live credit position: the total balance plus the grants behind it.
 */
final class CreditBalance extends Model
{
    /**
     * @param int                $balanceInKobo sum of remaining credit across active grants, integer kobo
     * @param list<CreditGrant>  $grants        the grants that make up this balance
     */
    public function __construct(
        public readonly string $domain,
        public readonly string $customerId,
        public readonly int $balanceInKobo,
        public readonly array $grants,
        array $raw = [],
    ) {
        parent::__construct($raw);
    }

    public static function fromArray(array $data): static
    {
        return new self(
            domain: Field::str($data, 'domain', 'credit_balance'),
            customerId: Field::str($data, 'customerId'),
            balanceInKobo: Field::int($data, 'balanceInKobo'),
            grants: array_map(
                static fn (array $row): CreditGrant => CreditGrant::fromArray($row),
                Field::objects($data, 'grants'),
            ),
            raw: $data,
        );
    }
}
