<?php

declare(strict_types=1);

namespace NombaOne\Exceptions;

/** A single attempt exceeded its timeout budget. Retried automatically. */
final class TimeoutException extends ConnectionException
{
}
