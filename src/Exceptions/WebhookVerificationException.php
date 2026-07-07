<?php

declare(strict_types=1);

namespace NombaOne\Exceptions;

/** Webhook signature or timestamp verification failed. Reject the delivery. */
final class WebhookVerificationException extends NombaOneException
{
}
