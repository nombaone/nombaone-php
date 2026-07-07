<?php

declare(strict_types=1);

namespace NombaOne\Tests\Unit;

use NombaOne\Exceptions\NombaOneException;
use NombaOne\Http\Request;
use NombaOne\Page;
use NombaOne\Tests\Support\MakesTestClient;
use NombaOne\Tests\Support\RecordingHttpClient;
use NombaOne\Tests\Support\StubModel;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
final class PaginationTest extends TestCase
{
    use MakesTestClient;

    public function testExposesTheFirstPageAndItsCursorBlock(): void
    {
        $http = new RecordingHttpClient();
        $http->page([['id' => 'a'], ['id' => 'b']], ['hasMore' => true, 'nextCursor' => 'c1']);

        $page = Page::fetch($this->makeClient($http), StubModel::class, new Request('GET', '/things', ['limit' => 2]));

        $this->assertCount(2, $page->data);
        $this->assertSame('a', $page->data[0]->id);
        $this->assertTrue($page->pagination->hasMore);
        $this->assertSame('c1', $page->pagination->nextCursor);
        $this->assertSame(2, $page->pagination->limit);
        $this->assertSame('req_test', $page->requestId);
    }

    public function testAutoPaginatesAcrossAllPagesThreadingCursorsAndPreservingFilters(): void
    {
        $http = new RecordingHttpClient();
        $http->page([['id' => 'a'], ['id' => 'b']], ['hasMore' => true, 'nextCursor' => 'c1'])
            ->page([['id' => 'c']], ['hasMore' => true, 'nextCursor' => 'c2'])
            ->page([['id' => 'd']], ['hasMore' => false, 'nextCursor' => null]);

        $client = $this->makeClient($http);
        $page = Page::fetch($client, StubModel::class, new Request('GET', '/things', ['status' => 'open', 'limit' => 2]));

        $ids = [];
        foreach ($page as $item) {
            $ids[] = $item->id;
        }

        $this->assertSame(['a', 'b', 'c', 'd'], $ids);
        $this->assertCount(3, $http->calls);

        // The cursor is threaded and the original filter is preserved on each page.
        $this->assertStringContainsString('cursor=c1', $http->calls[1]->pathWithQuery());
        $this->assertStringContainsString('status=open', $http->calls[1]->pathWithQuery());
        $this->assertStringContainsString('cursor=c2', $http->calls[2]->pathWithQuery());
        $this->assertStringContainsString('status=open', $http->calls[2]->pathWithQuery());
    }

    public function testManualPagingWithHasNextPageAndNextPage(): void
    {
        $http = new RecordingHttpClient();
        $http->page([['id' => 'a']], ['hasMore' => true, 'nextCursor' => 'c1'])
            ->page([['id' => 'b']], ['hasMore' => false, 'nextCursor' => null]);

        $client = $this->makeClient($http);
        $first = Page::fetch($client, StubModel::class, new Request('GET', '/things'));

        $this->assertTrue($first->hasNextPage());
        $second = $first->nextPage();
        $this->assertSame('b', $second->data[0]->id);
        $this->assertFalse($second->hasNextPage());
    }

    public function testNextPageThrowsWhenNoNextPageExists(): void
    {
        $http = new RecordingHttpClient();
        $http->page([['id' => 'a']], ['hasMore' => false, 'nextCursor' => null]);

        $page = Page::fetch($this->makeClient($http), StubModel::class, new Request('GET', '/things'));

        $this->expectException(NombaOneException::class);
        $this->expectExceptionMessageMatches('/No next page available/');
        $page->nextPage();
    }

    public function testIteratesASinglePageWithoutFetchingMore(): void
    {
        $http = new RecordingHttpClient();
        $http->page([['id' => 'a'], ['id' => 'b']], ['hasMore' => false, 'nextCursor' => null]);

        $page = Page::fetch($this->makeClient($http), StubModel::class, new Request('GET', '/things'));

        $ids = array_map(
            static fn (StubModel $model): string => $model->id,
            iterator_to_array($page->autoPagingIterator(), false),
        );

        $this->assertSame(['a', 'b'], $ids);
        $this->assertCount(1, $http->calls);
    }
}
