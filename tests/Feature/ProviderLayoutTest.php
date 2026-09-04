<?php

declare(strict_types=1);

/**
 * Every service provider lives in a Providers/ directory, with a namespace ending in \Providers.
 *
 * This is a layout rule, so it is asserted against the filesystem rather than the container: a
 * provider that drifts back to the root of src/ still boots perfectly well, which is exactly why
 * nothing else would notice. Copy this file into any package in the family.
 *
 * @return list<SplFileInfo>
 */
function providerFiles(string $src): array
{
    if (! is_dir($src)) {
        return [];
    }

    $found = [];

    /** @var iterable<SplFileInfo> $files */
    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($src, FilesystemIterator::SKIP_DOTS),
    );

    foreach ($files as $file) {
        if ($file->isFile() && str_ends_with($file->getFilename(), 'ServiceProvider.php')) {
            $found[] = $file;
        }
    }

    return $found;
}

it('keeps every service provider in a Providers directory', function (): void {
    $offenders = [];

    foreach (providerFiles(__DIR__ . '/../../src') as $file) {
        if (basename($file->getPath()) !== 'Providers') {
            $offenders[] = $file->getFilename();
        }
    }

    expect($offenders)->toBeEmpty();
});

it('ends every provider namespace in Providers', function (): void {
    $checked = 0;

    foreach (providerFiles(__DIR__ . '/../../src') as $file) {
        // The directory and the namespace are separate facts -- PSR-4 makes them agree only if the
        // file declares the namespace its path implies, and a bad move can leave them disagreeing.
        $source = file_get_contents($file->getPathname());

        preg_match('/^namespace\s+([^;]+);/m', $source === false ? '' : $source, $matches);

        expect($matches[1] ?? '')->toEndWith('\\Providers');
        $checked++;
    }

    expect($checked)->toBeGreaterThan(0);
});
