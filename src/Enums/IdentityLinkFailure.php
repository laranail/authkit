<?php

declare(strict_types=1);

namespace Simtabi\Laranail\AuthKit\Enums;

/**
 * Why a link attempt was refused.
 *
 * A closed set, so a caller rendering a message handles every case and a new reason cannot be
 * introduced without every `match` over it being revisited.
 */
enum IdentityLinkFailure: string
{
    /** No credential was confirmed recently enough to add a new way of signing in. */
    case RecentAuthenticationRequired = 'recent_authentication_required';

    /** The intent is unknown, expired, or has already been consumed. */
    case IntentInvalid = 'intent_invalid';

    /** The intent was issued to a different user, guard, provider, or authentication context. */
    case IntentMismatch = 'intent_mismatch';

    /** The external identity already belongs to a different account. */
    case IdentityOwnedByAnotherUser = 'identity_owned_by_another_user';

    public function message(): string
    {
        return match ($this) {
            self::RecentAuthenticationRequired => 'Confirm your password before adding a sign-in method.',
            self::IntentInvalid,
            self::IntentMismatch             => 'This linking request is no longer valid. Start again.',
            self::IdentityOwnedByAnotherUser => 'That account is already connected to a different user.',
        };
    }
}
