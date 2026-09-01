<?php

declare(strict_types=1);

namespace Simtabi\Laranail\AuthKit\Actions;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Auth;
use Simtabi\Laranail\AuthKit\Contracts\LogoutOtherBrowserSessionsInterface;
use Simtabi\Laranail\AuthKit\Services\BrowserSessionService;

class LogoutOtherBrowserSessions implements LogoutOtherBrowserSessionsInterface
{
    public function __construct(private readonly BrowserSessionService $sessions) {}

    public function execute(Authenticatable $user, ?string $currentSessionId = null): int
    {
        // Both halves are needed and neither is sufficient. Rotating the remembered password hash
        // is what actually stops the other browsers on their next request; deleting the rows is
        // what stops them still being listed as active devices afterwards.
        $guard = Auth::guard();

        if (method_exists($guard, 'logoutOtherDevices') && request()->hasSession()) {
            $password = request()->input('password');

            if (is_string($password) && $password !== '') {
                $guard->logoutOtherDevices($password);
            }
        }

        return $this->sessions->deleteOthersFor(user: $user, currentSessionId: $currentSessionId);
    }
}
