<?php

declare(strict_types=1);

namespace NombaOne\Exceptions;

/** 404 — no resource at that id in this environment (check sandbox vs live). */
final class NotFoundException extends ApiException
{
}
