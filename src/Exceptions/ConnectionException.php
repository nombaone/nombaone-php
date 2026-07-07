<?php

declare(strict_types=1);

namespace NombaOne\Exceptions;

/** The request never completed — DNS failure, connection reset, or TLS error. */
class ConnectionException extends NombaOneException
{
}
