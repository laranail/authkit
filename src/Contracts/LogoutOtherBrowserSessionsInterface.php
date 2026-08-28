<?php

declare(strict_types=1);

namespace Simtabi\Laranail\AuthKit\Contracts;

use Illuminate\Contracts\Auth\Authenticatable;

interface LogoutOtherBrowserSessionsInterface
{
    /**
     * Sign a user out everywhere except the browser making the request.
     *
     * Implementations must both invalidate the other sessions and remove their rows. Laravel's own
     * logoutOtherDevices() does only the first, which leaves the signed-out devices still listed
     * as active.
     *
     * @return int the number of other sessions ended
     */
    public function execute(Authenticatable $user, ?string $currentSessionId = null): int;
}
