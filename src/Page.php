<?php

declare(strict_types=1);

namespace NombaOne;

use NombaOne\Exceptions\NombaOneException;
use NombaOne\Http\ApiResponse;
use NombaOne\Http\Pagination;
use NombaOne\Http\Request;
use NombaOne\Models\Model;

/**
 * One page of a list result, plus everything needed to keep going.
 *
 * Works three ways:
 * - `$page->data` — the models on this page only.
 * - `$page->hasNextPage()` / `$page->nextPage()` — manual cursor paging.
 * - `foreach ($page as $item)` — auto-pagination across every following page,
 *   with cursors threaded for you (the original filters are preserved).
 *
 * @template T of Model
 *
 * @implements \IteratorAggregate<int, T>
 *
 * @example
 * ```php
 * // One page:
 * $page = $nomba->customers->list(['limit' => 50]);
 * $page->data;                    // list<Customer>
 * $page->pagination->hasMore;
 *
 * // Every customer, cursors handled for you:
 * foreach ($nomba->customers->list() as $customer) {
 *     echo $customer->email, "\n";
 * }
 * ```
 */
final class Page implements \IteratorAggregate
{
    /**
     * The models on this page.
     *
     * @var list<T>
     */
    public readonly array $data;

    /** The cursor block for this page: `limit`, `hasMore`, `nextCursor`. */
    public readonly Pagination $pagination;

    /** The request id for this page's fetch. */
    public readonly string $requestId;

    /**
     * @param class-string<T> $model
     */
    private function __construct(
        private readonly Nombaone $client,
        private readonly string $model,
        private readonly Request $request,
        private readonly ApiResponse $response,
    ) {
        $items = [];
        foreach ($response->data as $row) {
            if (is_array($row)) {
                $items[] = $model::fromArray($row)->withLastResponse($response);
            }
        }
        $this->data = $items;
        $this->pagination = $response->pagination ?? new Pagination(count($items), false, null);
        $this->requestId = $response->requestId;
    }

    /**
     * @template M of Model
     *
     * @param class-string<M> $model
     *
     * @return self<M>
     *
     * @internal
     */
    public static function fetch(Nombaone $client, string $model, Request $request): self
    {
        return new self($client, $model, $request, $client->send($request));
    }

    /** Whether another page exists after this one. */
    public function hasNextPage(): bool
    {
        return $this->pagination->hasMore && $this->pagination->nextCursor !== null;
    }

    /**
     * Fetch the next page — same filters, next cursor.
     *
     * @return self<T>
     *
     * @throws NombaOneException when there is no next page (check {@see hasNextPage()} first)
     */
    public function nextPage(): self
    {
        $cursor = $this->pagination->nextCursor;
        if ($cursor === null) {
            throw new NombaOneException(
                'No next page available — check hasNextPage() before calling nextPage().',
            );
        }

        $next = new Request(
            $this->request->method,
            $this->request->path,
            array_merge($this->request->query, ['cursor' => $cursor]),
            $this->request->body,
            $this->request->options,
        );

        return self::fetch($this->client, $this->model, $next);
    }

    /**
     * Iterate every item across this and all following pages, threading cursors.
     *
     * @return \Generator<int, T>
     */
    public function autoPagingIterator(): \Generator
    {
        $page = $this;
        while (true) {
            foreach ($page->data as $item) {
                yield $item;
            }
            if (!$page->hasNextPage()) {
                return;
            }
            $page = $page->nextPage();
        }
    }

    /**
     * @return \Generator<int, T>
     */
    public function getIterator(): \Generator
    {
        yield from $this->autoPagingIterator();
    }

    /** The full response (request id, headers, status) for this page's fetch. */
    public function getLastResponse(): ApiResponse
    {
        return $this->response;
    }
}
