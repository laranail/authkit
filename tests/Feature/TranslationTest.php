<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use Illuminate\Support\ServiceProvider;
use Simtabi\Laranail\AuthKit\Providers\AuthKitServiceProvider;

it('loads the packaged English messages and falls back from an untranslated locale', function (): void {
    expect(__('laranail/authkit::messages.invalid_credentials'))->toBe('Invalid credentials.');
    app()->setLocale('sw');
    expect(__('laranail/authkit::messages.invalid_credentials'))->toBe('Invalid credentials.');
});

it('publishes translations to the namespaced Laravel override directory', function (): void {
    $paths = ServiceProvider::pathsToPublish(AuthKitServiceProvider::class, 'laranail::authkit-translations');
    expect($paths)->toBe([dirname(__DIR__, 2) . '/lang' => lang_path('vendor/laranail/authkit')]);
});

it('honors application language-file overrides and retains other package defaults', function (): void {
    $directory = sys_get_temp_dir() . '/authkit-translations-' . bin2hex(random_bytes(8));
    $override = $directory . '/vendor/laranail/authkit/en';
    File::ensureDirectoryExists($override);
    File::put($override . '/messages.php', "<?php return ['invalid_credentials' => 'Application override'];");
    app('translation.loader')->addPath($directory);

    try {
        expect(__('laranail/authkit::messages.invalid_credentials'))->toBe('Application override');
        expect(__('laranail/authkit::messages.logged_out'))->toBe('Logged out successfully.');
    } finally {
        File::deleteDirectory($directory);
    }
});

it('uses translated messages in API responses and validation errors', function (): void {
    app('translator')->addLines([
        'messages.invalid_credentials'       => 'Localized login failure',
        'messages.current_password_mismatch' => 'Localized current password error',
    ], 'sw', 'laranail/authkit');
    app()->setLocale('sw');
    $this->postJson(route('laranail-auth-api.login'), ['email' => 'missing@example.com', 'password' => Simtabi\Laranail\Package\Tools\Testing\TestCredentials::password()])
        ->assertUnprocessable()->assertJsonPath('data.message', 'Localized login failure');
    expect(Simtabi\Laranail\AuthKit\Http\Requests\UpdatePasswordRequest::messagesFor()['current_password.current_password'])->toBe('Localized current password error');
});

it('translates identity-link failures while preserving their machine-readable reasons', function (): void {
    app('translator')->addLines(['messages.recent_authentication_required' => 'Localized confirmation'], 'sw', 'laranail/authkit');
    app()->setLocale('sw');
    $failure = Simtabi\Laranail\AuthKit\Enums\IdentityLinkFailure::RecentAuthenticationRequired;
    expect($failure->message())->toBe('Localized confirmation')->and($failure->value)->toBe('recent_authentication_required');
});
