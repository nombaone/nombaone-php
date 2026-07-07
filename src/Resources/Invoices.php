<?php

declare(strict_types=1);

namespace NombaOne\Resources;

use NombaOne\Http\Request;
use NombaOne\Models\Invoice;
use NombaOne\Page;

/**
 * Invoices — what billing cycles produced. Read and void only; the engine
 * creates invoices, you never do.
 */
final class Invoices extends Resource
{
    /**
     * Retrieve an invoice by id.
     *
     * @param array<string, mixed> $options
     *
     * @throws \NombaOne\Exceptions\NotFoundException 404 `INVOICE_NOT_FOUND`
     */
    public function retrieve(string $id, array $options = []): Invoice
    {
        return $this->requestModel(Invoice::class, new Request(
            'GET',
            '/invoices/' . self::seg($id),
            options: self::opts($options),
        ));
    }

    /**
     * List invoices, newest first.
     *
     * Note: the `status` filter accepts `draft|open|paid|void|uncollectible` —
     * it does **not** accept `partially_paid`, even though an invoice object
     * can carry that status.
     *
     * @param array{customerId?: string, subscriptionId?: string, status?: string, limit?: int, cursor?: string} $params
     * @param array<string, mixed> $options
     *
     * @return Page<Invoice>
     */
    public function list(array $params = [], array $options = []): Page
    {
        return $this->requestPage(Invoice::class, new Request(
            'GET',
            '/invoices',
            query: $params,
            options: self::opts($options),
        ));
    }

    /**
     * Void an invoice.
     *
     * @param array{comment?: string} $params
     * @param array<string, mixed>    $options
     *
     * @throws \NombaOne\Exceptions\ConflictException 409 `INVOICE_NOT_VOIDABLE`
     */
    public function void(string $id, array $params = [], array $options = []): Invoice
    {
        return $this->requestModel(Invoice::class, new Request(
            'POST',
            '/invoices/' . self::seg($id) . '/void',
            body: $params,
            options: self::opts($options),
        ));
    }
}
