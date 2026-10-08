<?php

declare(strict_types=1);

use Illuminate\Support\Str;
use Workbench\App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Simtabi\Laranail\AuthKit\Actions\ResetUserPassword;
use Simtabi\Laranail\AuthKit\Actions\SanctumTokenIssuer;
use Illuminate\Foundation\Auth\User as PlainAuthenticatable;

/*
 * revokeAll() runs on every password reset and update. It must revoke exactly the tokens of the
 * user it was given: never another model's tokens that share the id, and never fail for a user
 * model that does not issue Sanctum tokens at all.
 */

/** A second token-bearing model over the same table, as an Admin model would be. */
final class SanctumIssuerTestAdmin extends User
{
    protected $table = 'users';

    public function getMorphClass(): string
    {
        return 'sanctum-issuer-test-admin';
    }
}

/** A user model with no Sanctum tokens: no HasApiTokens, so no tokens() relation. */
final class SanctumIssuerTestPlainUser extends PlainAuthenticatable
{
    protected $table = 'users';
}

it('revokes every token of the given user', function (): void {
    $user = User::factory()->create();
    $user->createToken('one');
    $user->createToken('two');

    app(SanctumTokenIssuer::class)->revokeAll($user);

    expect($user->tokens()->count())->toBe(0);
});

it('leaves another model\'s tokens alone when the ids collide', function (): void {
    $user = User::factory()->create();
    $user->createToken('belongs-to-the-user');

    $admin = SanctumIssuerTestAdmin::query()->findOrFail($user->getKey());
    $admin->createToken('belongs-to-the-admin');

    app(SanctumTokenIssuer::class)->revokeAll($admin);

    expect($admin->tokens()->count())->toBe(0)
        ->and($user->tokens()->count())->toBe(1);
});

it('resets the password of a user model without Sanctum tokens', function (): void {
    $user = User::factory()->create();
    Schema::drop('personal_access_tokens');

    $plain = SanctumIssuerTestPlainUser::query()->findOrFail($user->getKey());

    $password = Str::password(16);

    app(ResetUserPassword::class)->reset($plain, [
        'password'              => $password,
        'password_confirmation' => $password,
    ]);

    expect(Hash::check($password, $plain->fresh()->password))->toBeTrue();
});
