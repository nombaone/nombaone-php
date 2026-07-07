<?php

declare(strict_types=1);

namespace NombaOne\Enums;

/**
 * The environment a key — and everything created with it — lives in.
 *
 * Encoded in the API key prefix: `nbo_sandbox_…` or `nbo_live_…`. Sandbox and
 * live are fully isolated: a resource created in one does not exist in the
 * other.
 *
 * Note: the wire emits `sandbox`/`live`. The OpenAPI spec advertises
 * `test`/`live`, which is wrong — the wire wins.
 */
enum Mode: string
{
    case Sandbox = 'sandbox';
    case Live = 'live';
}
