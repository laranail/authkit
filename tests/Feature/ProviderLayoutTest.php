<?php

declare(strict_types=1);

/**
 * Every service provider lives in a Providers/ directory, with a namespace ending in \Providers.
 *
 * This is a layout rule, so it is asserted against the filesystem rather than the container: a
 * provider that drifts back to the root of src/ still boots perfectly well, which is exactly why
 * nothing else would notice. Copy this file into any package in the family.
 */
it('keeps every service provider in a Providers directory', function (): void {
    $offenders = [];

    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator(__DIR__ . '/../../src', FilesystemIterator::SKIP_DOTS)
    );

    foreach ($files as $file) {
        if (! str_ends_with((string) $file->getFilename(), 'ServiceProvider.php')) {
            continue;
        }

        if (basename((string) $file->getPath()) !== 'Providers') {
            $offenders[] = $file->getFilename();
        }
    }

    expect($offenders)->toBeEmpty();
});

it('ends every provider namespace in Providers', function (): void {
    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator(__DIR__ . '/../../src', FilesystemIterator::SKIP_DOTS)
    );

    $checked = 0;

    foreach ($files as $file) {
        if (! str_ends_with((string) $file->getFilename(), 'ServiceProvider.php')) {
            continue;
        }

        // The directory and the namespace are separate facts -- PSR-4 makes them agree only if the
        // file declares the namespace the path implies, and a bad move can leave them disagreeing.
        preg_match('/^namespace\s+([^;]+);/m', (string) file_get_contents((string) $file->getPathname()), $m);

        expect($m[1] ?? '')->toEndWith('\\Providers');
        $checked++;
    }

    expect($checked)->toBeGreaterThan(0);
});
