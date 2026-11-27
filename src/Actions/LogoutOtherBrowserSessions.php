<?php

declare(strict_types=1);

namespace Simtabi\Laranail\AuthKit\Actions;

use Illuminate\Contracts\Auth\Authenticatable;
use Simtabi\Laranail\AuthKit\Services\BrowserSessionService;
use Simtabi\Laranail\AuthKit\Contracts\LogoutOtherBrowserSessionsInterface;

class LogoutOtherBrowserSessions implements LogoutOtherBrowserSessionsInterface
{
    public function __construct(
        private readonly BrowserSessionService $sessions,
        private \Illuminate\Contracts\Auth\Factory $auth,
        private \Illuminate\Http\Request $request,
    ) {}

    public function execute(Authenticatable $user, ?string $currentSessionId = null): int
    {
        // Both halves are needed and neither is sufficient. Rotating the remembered password hash
        // is what actually stops the other browsers on their next request; deleting the rows is
        // what stops them still being listed as active devices afterwards.
        $guard = $this->auth->guard();

        if (method_exists($guard, 'logoutOtherDevices') && $this->request->hasSession()) {
            $password = $this->request->input('password');

            if (is_string($password) && $password !== '') {
                $guard->logoutOtherDevices($password);
            }
        }

        return $this->sessions->deleteOthersFor(user: $user, currentSessionId: $currentSessionId);
    }
}
