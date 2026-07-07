<?php

declare(strict_types=1);

namespace NombaOne\Exceptions;

/**
 * Base class for everything this SDK throws — API failures, connection
 * problems, webhook-verification failures, and client misconfiguration.
 *
 * Catch this to handle any SDK error at once; catch a subclass to branch by
 * kind.
 *
 * @example
 * ```php
 * try {
 *     $nomba->subscriptions->create([...]);
 * } catch (\NombaOne\Exceptions\NombaOneException $e) {
 *     error_log($e->getMessage());
 * }
 * ```
 */
class NombaOneException extends \Exception
{
}
