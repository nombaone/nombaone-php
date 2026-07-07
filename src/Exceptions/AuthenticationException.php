<?php

declare(strict_types=1);

namespace NombaOne\Exceptions;

/** 401 — missing, invalid, revoked, or wrong-environment API key. */
final class AuthenticationException extends ApiException
{
}
