<?php

declare(strict_types=1);

namespace Simtabi\Laranail\AuthKit\Exceptions;

use RuntimeException;
use Simtabi\Laranail\AuthKit\Enums\IdentityLinkFailure;

/**
 * A link attempt was refused.
 *
 * The reason is a closed enum rather than a message, so a caller can branch on it -- a web flow
 * redirecting to a password-confirmation page and an API returning 403 want different handling of
 * the same refusal -- without parsing prose.
 */
final class IdentityLinkException extends RuntimeException
{
    private function __construct(public readonly IdentityLinkFailure $failure)
    {
        parent::__construct($failure->message());
    }

    public static function because(IdentityLinkFailure $failure): self
    {
        return new self($failure);
    }
}
