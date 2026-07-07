<?php

declare(strict_types=1);

namespace NombaOne\Exceptions;

/** 403 — valid key, but not allowed (missing scope, or a resource in another org). */
final class PermissionDeniedException extends ApiException
{
}
