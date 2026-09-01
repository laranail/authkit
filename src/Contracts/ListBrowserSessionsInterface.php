<?php

declare(strict_types=1);

namespace Simtabi\Laranail\AuthKit\Contracts;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Collection;

interface ListBrowserSessionsInterface
{
    /**
     * The browsers a user is currently signed in on, most recently active first.
     *
     * The device the request came from is flagged rather than omitted. An empty collection means
     * either that there is nothing to show or that sessions are not stored somewhere readable --
     * see BrowserSessionService::isSupported() to tell those apart before rendering "no other
     * devices" to someone checking their account security.
     *
     * @return Collection<int, object>
     */
    public function execute(Authenticatable $user, ?string $currentSessionId = null): Collection;
}
