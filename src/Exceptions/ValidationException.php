<?php

declare(strict_types=1);

namespace NombaOne\Exceptions;

/** 422 — one or more fields are invalid; read {@see ApiException::$fields}. */
final class ValidationException extends ApiException
{
}
