<?php

declare(strict_types=1);

use Illuminate\Support\ServiceProvider;

/**
 * The core registers no views, translations or routes — but it does own publish tags and a
 * configuration key, both of which are flat global registries. Asserted against the live
 * registry rather than the provider source, so the guard survives a refactor.
 */
it('never registers a bare publish tag', function (): void {
    // Testbench does not always populate publishableGroups(), so this asserts the invariant that
    // matters — nothing unscoped — rather than requiring the registry to be non-empty here. The
    // positive case is proved end-to-end in the demo application's acceptance suite.
    $bare = array_filter(
        array_keys(ServiceProvider::publishableGroups()),
        fn (string $tag): bool => str_contains($tag, 'authkit') && ! str_starts_with($tag, 'laranail::'),
    );

    expect(array_values($bare))->toBe([]);
});

it('keeps its configuration under the laranail namespace', function (): void {
    expect(config('laranail.authkit'))->toBeArray()
        ->and(config('auth-kit'))->toBeNull();
});

it('registers no bare top-level configuration key', function (): void {
    foreach (['auth-kit', 'authkit'] as $bare) {
        expect(config($bare))->toBeNull();
    }
});
