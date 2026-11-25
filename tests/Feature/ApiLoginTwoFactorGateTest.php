<?php

declare(strict_types=1);

use Workbench\App\Models\User;
use PragmaRX\Google2FA\Google2FA;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Simtabi\Laranail\Package\Tools\Testing\TestCredentials;
use Simtabi\Laranail\AuthKit\Services\TwoFactorAuthentication;

/**
 * Enrolment is the authority on whether a login needs a second factor, not a config flag.
 *
 * `Api\LoginController::apiPassed()` used to gate the challenge on
 * `AuthKit::twoFactorEnabled() && $service->enabled($user)`. The flag half is
 * `laranail.authkit.two_factor.enabled`, which the preset writes at register time from
 * Fortify's feature list -- so its value depends on which provider registered first, and
 * moving AuthKit's own Fortify configuration into `packageRegistered()` flipped it to false
 * for an application that had published `config/fortify.php` with two-factor on. The result
 * was a Sanctum token issued on a password alone to a user with TOTP enrolled and confirmed.
 *
 * The browser path never had this: `authkit-preset`'s `LoginController::handlePassed()` asks
 * the service and nothing else. The API path now matches it, which also removes the ordering
 * dependency rather than re-tuning it.
 *
 * The flag still does its job -- it gates the enrolment and challenge *endpoints*. What it no
 * longer does is let a login skip a second factor the user has already set up.
 */
trait TwoFactorFlagOff
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        // The state the regression produced: a user enrolled, and the flag off.
        $app['config']->set('laranail.authkit.two_factor.enabled', false);
        $app['config']->set('laranail.authkit.api.enabled', true);
    }
}

uses(TwoFactorFlagOff::class);

beforeEach(function (): void {
    Schema::table('users', function (Blueprint $table): void {
        $table->string('two_factor_method')->default('none');
    });

    $this->password = TestCredentials::password();
    $this->user = User::factory()->create(['password' => bcrypt($this->password)]);

    // Enrol and confirm, which needs the flag on; the request under test runs with it off.
    config()->set('laranail.authkit.two_factor.enabled', true);
    $twoFactor = app(TwoFactorAuthentication::class);
    $setup = $twoFactor->begin($this->user);
    $twoFactor->confirm($this->user, (new Google2FA)->getCurrentOtp($setup['secret']));
    config()->set('laranail.authkit.two_factor.enabled', false);
    cache()->flush();

    expect($twoFactor->enabled($this->user->fresh()))->toBeTrue();
});

it('challenges an enrolled user even when the two-factor flag is off', function (): void {
    $response = $this->postJson('api/auth/login', [
        'email'    => $this->user->email,
        'password' => $this->password,
    ]);

    $response->assertStatus(202);
    expect($response->json('status'))->toBe('mfa_required')
        ->and($response->json('data.challenge_token'))->toBeString();
});

it('issues no API token on the password alone to an enrolled user', function (): void {
    $response = $this->postJson('api/auth/login', [
        'email'    => $this->user->email,
        'password' => $this->password,
    ]);

    // The whole point: a bearer token here is the defect, whatever the status code says.
    $body = $response->json();
    $flat = json_encode($body);

    expect($response->json('data.token'))->toBeNull()
        ->and($response->json('data.access_token'))->toBeNull()
        ->and($flat)->not->toContain('plainTextToken');
});
