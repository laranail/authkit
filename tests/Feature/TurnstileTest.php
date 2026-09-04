<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Validator;
use Simtabi\Laranail\AuthKit\Support\AuthKit;
use Simtabi\Laranail\AuthKit\Rules\TurnstileRule;

function useTurnstileRoute(string $middleware): void
{
    $route = Route::post('/turnstile-test', static fn () => null)->middleware($middleware);
    $request = Request::create(uri: '/turnstile-test', method: 'POST');
    $request->setRouteResolver(static fn () => $route);

    app()->instance('request', $request);
}

beforeEach(function (): void {
    config()->set('laranail.authkit.turnstile.enabled', true);
    config()->set('laranail.authkit.turnstile.secret_key', 'secret-key');
});

it('accepts a valid token on web requests', function (): void {
    useTurnstileRoute(middleware: 'web');
    Http::fake(['*' => Http::response(['success' => true])]);

    $validator = Validator::make(
        data: ['cf-turnstile-response' => 'valid-token'],
        rules: ['cf-turnstile-response' => AuthKit::turnstileRules()],
    );

    expect($validator->passes())->toBeTrue();
    Http::assertSentCount(1);
});

it('rejects an invalid token', function (): void {
    useTurnstileRoute(middleware: 'web');
    Http::fake(['*' => Http::response(['success' => false])]);

    $validator = Validator::make(
        data: ['cf-turnstile-response' => 'invalid-token'],
        rules: ['cf-turnstile-response' => [new TurnstileRule]],
    );

    expect($validator->fails())->toBeTrue()
        ->and($validator->errors()->has('cf-turnstile-response'))->toBeTrue();
});

it('rejects a missing token on web requests', function (): void {
    useTurnstileRoute(middleware: 'web');
    Http::fake();

    $validator = Validator::make(
        data: [],
        rules: ['cf-turnstile-response' => AuthKit::turnstileRules()],
    );

    expect($validator->fails())->toBeTrue();
    Http::assertNothingSent();
});

it('does not add rules when Turnstile is disabled', function (): void {
    config()->set('laranail.authkit.turnstile.enabled', false);
    useTurnstileRoute(middleware: 'web');

    expect(AuthKit::turnstileRules())->toBe([]);
});

it('does not add rules to API requests', function (): void {
    useTurnstileRoute(middleware: 'api');

    expect(AuthKit::turnstileRules())->toBe([]);
});
