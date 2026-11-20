<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

it('adds and removes the two-factor fields on the configured user table', function (): void {
    $table = 'authkit_two_factor_migration_users';

    config()->set('laranail.authkit.two_factor.table', $table);

    Schema::create($table, function (Blueprint $table): void {
        $table->id();
    });

    $migration = require dirname(__DIR__, 2) . '/database/migrations/two-factor/2026_10_02_000000_add_two_factor_authentication_to_users_table.php';

    $migration->up();

    expect(Schema::hasColumns($table, [
        'two_factor_method',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'two_factor_confirmed_at',
    ]))->toBeTrue();

    $migration->down();

    expect(Schema::hasColumns($table, [
        'two_factor_method',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'two_factor_confirmed_at',
    ]))->toBeFalse();

    Schema::dropIfExists($table);
});
