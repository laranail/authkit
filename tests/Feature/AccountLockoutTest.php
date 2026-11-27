<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Workbench\App\Models\User;
use Illuminate\Cache\RateLimiter;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Simtabi\Laranail\AuthKit\Enums\AuthStatus;
use Simtabi\Laranail\AuthKit\Events\AccountLocked;
use Simtabi\Laranail\AuthKit\Events\AccountUnlocked;
use Simtabi\Laranail\AuthKit\Services\AccountLockout;
use Simtabi\Laranail\AuthKit\Events\AuthenticationFailed;
use Simtabi\Laranail\Package\Tools\Testing\TestCredentials;
use Simtabi\Laranail\AuthKit\Actions\AttemptEmailPasswordLogin;
use Simtabi\Laranail\AuthKit\Contracts\IssueTokenForUserInterface;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Simtabi\Laranail\AuthKit\Http\Controllers\AbstractAttemptEmailPasswordLoginController;

final class LockoutWebLoginController extends AbstractAttemptEmailPasswordLoginController {}

class OtherLockoutPopulationUser extends User
{
    protected $table = 'deletion_test_users';
}

class EmailIdentifierLockoutUser extends User
{
    protected $table = 'users';

    public function getAuthIdentifierName()
    {
        return 'email';
    }
}

trait AccountLockoutEnvironment
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);
        $app['config']->set('laranail.authkit.lockout.enabled', true);
        $app['config']->set('laranail.authkit.lockout.max_attempts', 3);
        $app['config']->set('laranail.authkit.lockout.duration_minutes', 2);
        $app['config']->set('laranail.authkit.rate_limit.max_attempts', 100);
        $app['config']->set('laranail.authkit.two_factor.enabled', true);
    }
}

uses(AccountLockoutEnvironment::class);

beforeEach(function (): void {
    $this->password = TestCredentials::password('lockout');
    $this->user = User::factory()->create(['password' => $this->password, 'username' => 'lockout-user']);
    Route::post('/lockout-web-login', [LockoutWebLoginController::class, 'store'])->middleware('web');
});

function lockoutAttempt(string $identifier, string $password, string $ip = '198.51.100.1', string $guard = 'web')
{
    $field = config('laranail.authkit.login.field', 'email');
    $request = Request::create('/login', 'POST', [$field => $identifier, 'password' => $password], server: ['REMOTE_ADDR' => $ip]);

    return app(AttemptEmailPasswordLogin::class)->execute($request, $guard);
}

it('locks at the configured threshold and blocks the correct password on another IP', function (): void {
    Event::fake([AccountLocked::class, AccountUnlocked::class, AuthenticationFailed::class]);
    $wrong = TestCredentials::password('incorrect');
    for ($i = 0; $i < 2; $i++) {
        expect(lockoutAttempt($this->user->email, $wrong)->status)->toBe(AuthStatus::Failed);
    }
    $result = lockoutAttempt($this->user->email, $wrong);
    expect($result->status)->toBe(AuthStatus::Locked)->and($result->retryAfterSeconds)->toBe(120)
        ->and(lockoutAttempt($this->user->email, $this->password, '198.51.100.2')->status)->toBe(AuthStatus::Locked);
    Event::assertDispatchedTimes(AccountLocked::class, 1);
    Event::assertNotDispatched(AccountUnlocked::class);
    Event::assertDispatched(AuthenticationFailed::class, fn ($event) => $event->reason === 'locked');
    $this->assertGuest();
});

it('persists account lockout independently of request limiter resets and cache flushes', function (): void {
    config(['laranail.authkit.rate_limit.max_attempts' => 1]);
    for ($i = 0; $i < 3; $i++) {
        lockoutAttempt($this->user->email, TestCredentials::password('incorrect'), '198.51.100.' . ($i + 1));
    }
    app(RateLimiter::class)->clear('login:web:' . $this->user->email . ':198.51.100.1');
    Cache::flush();
    expect(lockoutAttempt($this->user->email, $this->password)->status)->toBe(AuthStatus::Locked);
    $this->travel(61)->seconds();
    expect(lockoutAttempt($this->user->email, $this->password, '198.51.100.9')->status)->toBe(AuthStatus::Locked);
});

it('expires automatically and begins a new failure sequence without extending an active lock', function (): void {
    Event::fake([AccountUnlocked::class, AccountLocked::class]);
    $lockout = app(AccountLockout::class);
    for ($i = 0; $i < 3; $i++) {
        $lockout->recordFailure($this->user, 'web');
    }
    $this->travel(60)->seconds();
    expect($lockout->recordFailure($this->user, 'web'))->toBe(60);
    $this->travel(60)->seconds();
    expect($lockout->recordFailure($this->user, 'web'))->toBe(0)
        ->and(DB::table('authkit_account_lockouts')->value('failed_attempts'))->toBe(1);
    Event::assertDispatched(AccountUnlocked::class, fn ($event) => $event->reason === 'expired');
    Event::assertDispatchedTimes(AccountLocked::class, 1);
    expect(lockoutAttempt($this->user->email, $this->password)->status)->toBe(AuthStatus::Passed);
});

it('clears explicitly and resets partial failures after a valid credential check', function (): void {
    Event::fake([AccountUnlocked::class]);
    $lockout = app(AccountLockout::class);
    for ($i = 0; $i < 3; $i++) {
        $lockout->recordFailure($this->user, 'web');
    }
    $lockout->clear($this->user, 'web');
    Event::assertDispatched(AccountUnlocked::class, fn ($event) => $event->reason === 'manual');
    $lockout->recordFailure($this->user, 'web');
    $lockout->recordFailure($this->user, 'web');
    expect(lockoutAttempt($this->user->email, $this->password)->isPassed())->toBeTrue();
    expect($lockout->recordFailure($this->user, 'web'))->toBe(0)
        ->and(DB::table('authkit_account_lockouts')->value('failed_attempts'))->toBe(1);
});

it('shares a lock across email username and guards for the same physical account', function (): void {
    config(['laranail.authkit.login.field' => 'login', 'laranail.authkit.login.identifiers' => ['email', 'username'], 'auth.guards.admin' => ['driver' => 'session', 'provider' => 'users']]);
    lockoutAttempt($this->user->email, TestCredentials::password('incorrect'));
    lockoutAttempt($this->user->username, TestCredentials::password('incorrect'), guard: 'admin');
    $result = lockoutAttempt($this->user->email, TestCredentials::password('incorrect'));
    expect($result->status)->toBe(AuthStatus::Locked)
        ->and(lockoutAttempt($this->user->username, $this->password, guard: 'admin')->status)->toBe(AuthStatus::Locked)
        ->and(DB::table('authkit_account_lockouts')->count())->toBe(1);
});

it('counts one failure when multiple columns resolve the same account', function (): void {
    config(['laranail.authkit.login.identifiers' => ['email', 'username']]);
    $this->user->forceFill(['username' => $this->user->email])->save();
    lockoutAttempt($this->user->email, TestCredentials::password('incorrect'));
    expect(DB::table('authkit_account_lockouts')->value('failed_attempts'))->toBe(1);
});

it('does not penalize an email candidate when the overlapping username owner signs in', function (): void {
    config(['laranail.authkit.login.identifiers' => ['email', 'username']]);
    $secondPassword = TestCredentials::password('second');
    $second = User::factory()->create(['username' => $this->user->email, 'password' => $secondPassword]);
    expect(lockoutAttempt($this->user->email, $secondPassword)->user->is($second))->toBeTrue()
        ->and(app(AccountLockout::class)->retryAfter($this->user, 'web'))->toBe(0)
        ->and(DB::table('authkit_account_lockouts')->where('account_key', app(AccountLockout::class)->keyFor($this->user, 'web'))->exists())->toBeFalse();
});

it('does not lock another account or another model population with an identical ID', function (): void {
    $other = User::factory()->create(['password' => $this->password]);
    $population = new OtherLockoutPopulationUser;
    $population->forceFill(['id' => $this->user->getKey(), 'name' => 'Another population', 'email' => 'population@example.test', 'password' => $this->password])->save();
    $lockout = app(AccountLockout::class);
    for ($i = 0; $i < 3; $i++) {
        $lockout->recordFailure($this->user, 'web');
    }
    expect($lockout->retryAfter($other, 'web'))->toBe(0)
        ->and($lockout->retryAfter($population, 'web'))->toBe(0)
        ->and(lockoutAttempt($other->email, $this->password)->isPassed())->toBeTrue();
});

it('does not persist attacker supplied identifiers that resolve no account', function (): void {
    for ($i = 0; $i < 4; $i++) {
        expect(lockoutAttempt('absent@example.test', TestCredentials::password('incorrect'))->status)->toBe(AuthStatus::Failed);
    }
    expect(DB::table('authkit_account_lockouts')->count())->toBe(0);
});

it('blocks both browser and API credential login with retry information', function (string $surface): void {
    $path = $surface === 'api' ? '/api/auth/login' : '/lockout-web-login';
    for ($i = 0; $i < 2; $i++) {
        $this->postJson($path, ['email' => $this->user->email, 'password' => TestCredentials::password('incorrect')])->assertUnprocessable();
    }
    $this->postJson($path, ['email' => $this->user->email, 'password' => TestCredentials::password('incorrect')])->assertStatus(429)->assertJsonPath('status', 'locked')->assertHeader('Retry-After', '120');
    Cache::flush();
    $this->postJson($path, ['email' => $this->user->email, 'password' => $this->password])->assertStatus(429)->assertJsonPath('status', 'locked')->assertJsonPath('data.retry_after', 120);
    expect($this->user->tokens()->count())->toBe(0);
    $this->assertGuest();
    $this->travel(121)->seconds();
    $this->postJson($path, ['email' => $this->user->email, 'password' => $this->password])->assertOk();
})->with(['api', 'web']);

it('returns a browser lockout response for an ordinary form submission', function (): void {
    $lockout = app(AccountLockout::class);
    for ($i = 0; $i < 3; $i++) {
        $lockout->recordFailure($this->user, 'web');
    }
    $this->post('/lockout-web-login', ['email' => $this->user->email, 'password' => $this->password])->assertStatus(429)->assertHeader('Retry-After', '120');
    $this->assertGuest();
});

it('rejects pending MFA before consuming its factor proof when the account became locked', function (): void {
    $challenge = bin2hex(random_bytes(32));
    $key = 'authkit:two-factor:challenge:' . hash('sha256', $challenge);
    Cache::put($key, ['user_id' => $this->user->getKey(), 'guard' => 'web'], 300);
    for ($i = 0; $i < 3; $i++) {
        app(AccountLockout::class)->recordFailure($this->user, 'web');
    }
    $this->postJson('/api/auth/two-factor/challenge', ['challenge_token' => $challenge, 'code' => substr(TestCredentials::secret('factor'), 0, 6)])->assertStatus(429)->assertHeader('Retry-After', '120');
    expect(Cache::has($key))->toBeTrue()->and($this->user->tokens()->count())->toBe(0);
});

it('blocks direct session sign-ins and removes their staged login session', function (): void {
    for ($i = 0; $i < 3; $i++) {
        app(AccountLockout::class)->recordFailure($this->user, 'web');
    }
    expect(fn () => auth('web')->login($this->user, true))->toThrow(TooManyRequestsHttpException::class);
    $this->assertGuest('web');
    expect(session()->has(auth('web')->getName()))->toBeFalse();
});

it('blocks new token issuance while the account is locked', function (): void {
    for ($i = 0; $i < 3; $i++) {
        app(AccountLockout::class)->recordFailure($this->user, 'web');
    }
    expect(fn () => app(IssueTokenForUserInterface::class)->execute($this->user))->toThrow(TooManyRequestsHttpException::class);
    expect($this->user->tokens()->count())->toBe(0);
});

it('performs no lockout storage queries when disabled', function (): void {
    config(['laranail.authkit.lockout.enabled' => false]);
    $queries = [];
    DB::listen(function ($query) use (&$queries): void {
        $queries[] = $query->sql;
    });
    for ($i = 0; $i < 4; $i++) {
        lockoutAttempt($this->user->email, TestCredentials::password('incorrect'));
    }
    expect(lockoutAttempt($this->user->email, $this->password)->isPassed())->toBeTrue()
        ->and(implode(' ', $queries))->not->toContain('authkit_account_lockouts');
});

it('removes the lockout record when its account is deleted', function (): void {
    $lockout = app(AccountLockout::class);
    for ($i = 0; $i < 3; $i++) {
        $lockout->recordFailure($this->user, 'web');
    }
    app(Simtabi\Laranail\AuthKit\Contracts\DeleteUserInterface::class)->execute($this->user, $this->password);
    expect(DB::table('authkit_account_lockouts')->where('account_key', $lockout->keyFor($this->user, 'web'))->exists())->toBeFalse();
});

it('shares the physical account lock when a model alias changes its authentication identifier column', function (): void {
    $lockout = app(AccountLockout::class);
    for ($i = 0; $i < 3; $i++) {
        $lockout->recordFailure($this->user, 'web');
    }
    $alias = EmailIdentifierLockoutUser::findOrFail($this->user->getKey());
    expect($lockout->retryAfter($alias, 'alias'))->toBe(120);
    $lockout->clear($alias, 'alias');
    expect($lockout->retryAfter($this->user, 'web'))->toBe(0);
});
