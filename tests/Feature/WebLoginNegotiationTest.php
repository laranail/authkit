<?php

declare(strict_types=1);

use Workbench\App\Models\User;
use Illuminate\Support\Facades\Route;
use Simtabi\Laranail\Package\Tools\Testing\TestCredentials;
use Simtabi\Laranail\AuthKit\Http\Controllers\AbstractAttemptEmailPasswordLoginController;

/**
 * The core ships no web routes — the web surface lives in laranail/authkit-preset — so the
 * abstract controller is mounted here to be exercised directly. A bare subclass is enough: the
 * parent supplies `guard()`.
 */
final class WebLoginNegotiationController extends AbstractAttemptEmailPasswordLoginController {}

beforeEach(function (): void {
    Route::post('/web-login-negotiation', [WebLoginNegotiationController::class, 'store'])
        ->middleware('web');

    $this->password = TestCredentials::password();
    $this->user = User::factory()->create([
        'email'    => 'ada@example.com',
        'password' => bcrypt($this->password),
    ]);
});

// `store()` negotiated the representation *before* authenticating: on `expectsJson()` it returned
// `200 {"status":"passed"}` straight from the match and never reached `handlePassed()`, which is
// the only caller of `LoginUserInterface::execute()`. So the caller was told it had signed in and
// no session existed. It fails closed, but an application posting its login form with fetch has a
// login that silently never works — and in the preset, `handlePassed()` is also where the
// two-factor gate lives.
it('signs the user in when the login is posted as JSON', function (): void {
    $this->postJson('/web-login-negotiation', [
        'email'    => 'ada@example.com',
        'password' => $this->password,
    ])->assertOk()->assertJsonPath('status', 'passed');

    $this->assertAuthenticated();
});

it('still redirects and signs in when the same login is posted as a form', function (): void {
    $this->post('/web-login-negotiation', [
        'email'    => 'ada@example.com',
        'password' => $this->password,
    ])->assertRedirect();

    $this->assertAuthenticated();
});

it('reports a bad JSON login as failed and leaves the caller a guest', function (): void {
    $this->postJson('/web-login-negotiation', [
        'email'    => 'ada@example.com',
        'password' => TestCredentials::password('wrong'),
    ])->assertStatus(422)->assertJsonPath('status', 'failed');

    $this->assertGuest();
});
