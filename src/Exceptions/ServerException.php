<?php

declare(strict_types=1);

namespace NombaOne\Exceptions;

/** 5xx — something failed on NombaOne's side; safe to retry (the SDK already did). */
final class ServerException extends ApiException
{
}
