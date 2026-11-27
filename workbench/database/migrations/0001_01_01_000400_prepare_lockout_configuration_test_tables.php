<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Migrations\Migration;

// Server-database tests share their migrated schema across application instances. Prepare both
// configurations before RefreshDatabase begins transactions, using the package's actual migration.
return new class extends Migration
{
    public function up(): void
    {
        $table = config('laranail.authkit.lockout.table');
        $connection = config('laranail.authkit.lockout.connection');
        try {
            foreach (['authkit_account_lockouts', 'custom_authkit_lockouts'] as $name) {
                // The package migration runs later and creates the currently configured table.
                if ($name === $table) {
                    continue;
                }
                config(['laranail.authkit.lockout.table' => $name, 'laranail.authkit.lockout.connection' => 'testing']);
                if (! Schema::connection('testing')->hasTable($name)) {
                    $migration = require dirname(__DIR__, 3) . '/database/migrations/lockout/2026_11_27_000000_create_authkit_account_lockouts_table.php';
                    $migration->up();
                }
            }
        } finally {
            config(['laranail.authkit.lockout.table' => $table, 'laranail.authkit.lockout.connection' => $connection]);
        }
    }

    public function down(): void
    {
        Schema::connection('testing')->dropIfExists('custom_authkit_lockouts');
        Schema::connection('testing')->dropIfExists('authkit_account_lockouts');
    }
};
