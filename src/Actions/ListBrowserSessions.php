<?php

declare(strict_types=1);

namespace Simtabi\Laranail\AuthKit\Actions;

use Illuminate\Support\Collection;
use Illuminate\Contracts\Auth\Authenticatable;
use Simtabi\Laranail\AuthKit\Services\BrowserSessionService;
use Simtabi\Laranail\AuthKit\Contracts\ListBrowserSessionsInterface;

class ListBrowserSessions implements ListBrowserSessionsInterface
{
    public function __construct(private readonly BrowserSessionService $sessions) {}

    /** @return Collection<int, object> */
    public function execute(Authenticatable $user, ?string $currentSessionId = null): Collection
    {
        return $this->sessions->forUser(user: $user, currentSessionId: $currentSessionId);
    }
}
