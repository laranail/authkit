<?php

declare(strict_types=1);

namespace Simtabi\Laranail\AuthKit\Actions;

use Illuminate\Validation\Rules\Password;
use Simtabi\Laranail\AuthKit\Support\AuthKit;
use Laravel\Fortify\Contracts\UpdatesUserPasswords;
use Simtabi\Laranail\AuthKit\Services\BrowserSessionService;
use Simtabi\Laranail\AuthKit\Services\UserValidationService;
use Simtabi\Laranail\AuthKit\Http\Requests\UpdatePasswordRequest;
use Simtabi\Laranail\AuthKit\Contracts\TokenIssuerRegistryInterface;

class UpdateUserPassword implements UpdatesUserPasswords
{
    public function __construct(
        private \Illuminate\Contracts\Hashing\Hasher $hasher,
        private UserValidationService $validation,
        private TokenIssuerRegistryInterface $issuers,
        private \Illuminate\Contracts\Auth\Factory $auth,
        private \Illuminate\Http\Request $request,
        private BrowserSessionService $sessions,
    ) {}

    public function update($user, array $input): void
    {
        $guard = AuthKit::guard();

        $this->validation->validate(
            input: $input,
            rules: UpdatePasswordRequest::rulesFor(guard: $guard),
            messages: UpdatePasswordRequest::messagesFor(),
            errorBag: 'updatePassword',
        );

        $user->forceFill(attributes: [
            'password'       => $this->hasher->make(value: $input['password']),
            'remember_token' => null,
        ])->save();

        $this->issuers->revokeAll($user);

        $guardInstance = $this->auth->guard($guard);

        if ($guardInstance->id() === $user->getAuthIdentifier() && method_exists($guardInstance, 'logoutOtherDevices')) {
            $guardInstance->logoutOtherDevices($input['password']);

            // logoutOtherDevices() only rotates the remembered password hash, which stops the
            // other browsers on their next request but leaves their rows in the sessions table.
            // Those rows keep the devices listed as active, so someone who changed their password
            // precisely because a laptop was stolen still sees it signed in. Remove them too.
            $this->sessions->deleteOthersFor(
                user: $user,
                currentSessionId: $this->request->hasSession() ? $this->request->session()->getId() : null,
            );
        }
    }
}
