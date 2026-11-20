<?php

declare(strict_types=1);

use Workbench\App\Models\User;
use PragmaRX\Google2FA\Google2FA;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Simtabi\Laranail\AuthKit\Services\TwoFactorAuthentication;

trait RecoveryCodeEnvironment
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);
        $app['config']->set('laranail.authkit.two_factor.enabled', true);
    }
}

uses(RecoveryCodeEnvironment::class);

beforeEach(function (): void {
    Schema::table('users', function (Blueprint $table): void {
        $table->string('two_factor_method')->default('none');
    });

    $this->user = User::factory()->create();
    $this->twoFactor = app(TwoFactorAuthentication::class);
    $setup = $this->twoFactor->begin($this->user);
    $this->secret = $setup['secret'];
    $codes = $this->twoFactor->confirm($this->user, (new Google2FA)->getCurrentOtp($this->secret));
    cache()->flush();
    $this->code = $codes[0];
    expect(strlen($this->code))->toBe(21);
});

function apiRecoveryChallenge($test): string
{
    $token = bin2hex(random_bytes(32));
    cache()->put('authkit:two-factor:challenge:' . hash('sha256', $token), ['user_id' => $test->user->id, 'guard' => 'web'], now()->addMinutes(5));

    return $token;
}

it('accepts a full recovery code for API login and rejects reuse in a fresh challenge', function (): void {
    $this->postJson(route('laranail-auth-api.two-factor.challenge'), ['challenge_token' => apiRecoveryChallenge($this), 'code' => $this->code])->assertOk()->assertJsonPath('status', 'success')->assertJsonStructure(['data' => ['token']]);
    expect($this->twoFactor->recoveryCodes($this->user->fresh()))->not->toContain($this->code);
    $this->postJson(route('laranail-auth-api.two-factor.challenge'), ['challenge_token' => apiRecoveryChallenge($this), 'code' => $this->code])->assertUnprocessable()->assertJsonValidationErrors('code');
});

it('rejects an invalid recovery code', function (): void {
    $this->postJson(route('laranail-auth-api.two-factor.challenge'), ['challenge_token' => apiRecoveryChallenge($this), 'code' => 'invalidcode-invalidco'])->assertUnprocessable()->assertJsonValidationErrors('code');
    expect($this->user->tokens()->count())->toBe(0);
});

it('still accepts a six-digit authenticator code', function (): void {
    $this->postJson(route('laranail-auth-api.two-factor.challenge'), ['challenge_token' => apiRecoveryChallenge($this), 'code' => (new Google2FA)->getCurrentOtp($this->secret)])->assertOk();
});

it('accepts a recovery code when disabling two-factor authentication', function (): void {
    $this->actingAs($this->user, 'sanctum')->postJson(route('laranail-auth-api.user-two-factor.disable'), ['code' => $this->code])->assertOk();
    expect($this->twoFactor->enabled($this->user->fresh()))->toBeFalse();
});

it('accepts a recovery code when regenerating codes', function (): void {
    $this->actingAs($this->user, 'sanctum')->postJson(route('laranail-auth-api.user-two-factor.recovery-codes'), ['code' => $this->code])->assertOk()->assertJsonCount(8, 'data.recovery_codes');
    expect($this->twoFactor->recoveryCodes($this->user->fresh()))->not->toContain($this->code);
});
