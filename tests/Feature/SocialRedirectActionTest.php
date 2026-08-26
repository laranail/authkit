<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Laravel\Socialite\Facades\Socialite;
use Illuminate\Support\Facades\Validator;
use Simtabi\Laranail\Enumerator\Rules\EnumValue;
use Simtabi\Laranail\AuthKit\Enums\SocialProvider;
use Simtabi\Laranail\AuthKit\Actions\SocialRedirectAction;

function redirectRequest(string $provider): Request
{
    $request = Request::create(uri: "/auth/social/{$provider}", method: 'GET');
    $request->setRouteResolver(fn () => (new Route('GET', "/auth/social/{provider}", []))->bind($request));
    $request->route()->setParameter('provider', $provider);

    return $request;
}

it(description: 'returns redirect url for google', closure: function (): void {
    Socialite::fake(driver: SocialProvider::GOOGLE->value);

    $action = app(abstract: SocialRedirectAction::class);

    $result = $action->execute(request: redirectRequest('google'));

    expect(value: $result->url)->toBeString()->not->toBeEmpty();
});

it(description: 'returns redirect url for facebook', closure: function (): void {
    Socialite::fake(driver: SocialProvider::FACEBOOK->value);

    $action = app(abstract: SocialRedirectAction::class);

    $result = $action->execute(request: redirectRequest('facebook'));

    expect(value: $result->url)->toBeString()->not->toBeEmpty();
});

it(description: 'returns redirect url for twitter', closure: function (): void {
    Socialite::fake(driver: SocialProvider::TWITTER->value);

    $action = app(abstract: SocialRedirectAction::class);

    $result = $action->execute(request: redirectRequest('twitter'));

    expect(value: $result->url)->toBeString()->not->toBeEmpty();
});

it(description: 'returns redirect url for linkedin', closure: function (): void {
    Socialite::fake(driver: SocialProvider::LINKEDIN->value);

    $action = app(abstract: SocialRedirectAction::class);

    $result = $action->execute(request: redirectRequest('linkedin'));

    expect(value: $result->url)->toBeString()->not->toBeEmpty();
});

it(description: 'returns redirect url for paypal', closure: function (): void {
    Socialite::fake(driver: SocialProvider::PAYPAL->value);

    $action = app(abstract: SocialRedirectAction::class);

    $result = $action->execute(request: redirectRequest('paypal'));

    expect(value: $result->url)->toBeString()->not->toBeEmpty();
});

it('exposes Enumerator labels and collection helpers without changing provider values', function (): void {
    expect(SocialProvider::values())->toBe([
        'google',
        'facebook',
        'twitter',
        'linkedin',
        'paypal',
    ])
        ->and(SocialProvider::labels())->toBe([
            'google'   => 'Google',
            'facebook' => 'Facebook',
            'twitter'  => 'X (Twitter)',
            'linkedin' => 'LinkedIn',
            'paypal'   => 'PayPal',
        ])
        ->and(SocialProvider::collect()->flatValues())->toBe(SocialProvider::values())
        ->and(SocialProvider::GOOGLE->label())->toBe('Google');
});

it('validates social provider values with Enumerator', function (): void {
    expect(Validator::make(
        data: ['provider' => 'google'],
        rules: ['provider' => [new EnumValue(SocialProvider::class)]],
    )->passes())->toBeTrue()
        ->and(Validator::make(
            data: ['provider' => 'github'],
            rules: ['provider' => [new EnumValue(SocialProvider::class)]],
        )->fails())->toBeTrue();
});
