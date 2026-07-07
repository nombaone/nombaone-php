<?php

declare(strict_types=1);

namespace NombaOne\Exceptions;

/** 409 — conflicts with current state (including idempotency reuse / in-progress). */
final class ConflictException extends ApiException
{
}
