<?php

declare(strict_types=1);

/**
 * Pagination — every `list()` works three ways.
 *
 * Run:  NOMBAONE_API_KEY=nbo_sandbox_… php examples/02-pagination.php
 */

require __DIR__ . '/../vendor/autoload.php';

use NombaOne\Nombaone;

$nomba = new Nombaone(getenv('NOMBAONE_API_KEY'));

// 1) One page.
$page = $nomba->customers->list(['limit' => 3]);
echo 'page 1: ' . count($page->data) . ' customers, hasMore=' . ($page->pagination->hasMore ? 'yes' : 'no') . "\n";
foreach ($page->data as $customer) {
    echo "  - {$customer->email}\n";
}

// 2) Manual paging — thread the cursor yourself.
if ($page->hasNextPage()) {
    $next = $page->nextPage();
    echo 'page 2: ' . count($next->data) . " customers (via nextPage)\n";
}

// 3) Auto-iteration — cursors handled for you. `foreach` walks every page.
$total = 0;
foreach ($nomba->customers->list(['limit' => 5]) as $customer) {
    $total++;
    if ($total >= 12) {
        break; // stop early; the SDK only fetches pages as you consume them
    }
}
echo "auto-iterated {$total} customers across pages\n";
