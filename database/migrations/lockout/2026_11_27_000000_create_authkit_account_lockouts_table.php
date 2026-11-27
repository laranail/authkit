<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection(config('laranail.authkit.lockout.connection'))
            ->create(config('laranail.authkit.lockout.table', 'authkit_account_lockouts'), function (Blueprint $table): void {
                $table->string('account_key', 64)->primary();
                $table->unsignedInteger('failed_attempts')->default(0);
                $table->unsignedBigInteger('locked_until')->nullable();
            });
    }

    public function down(): void
    {
        Schema::connection(config('laranail.authkit.lockout.connection'))
            ->dropIfExists(config('laranail.authkit.lockout.table', 'authkit_account_lockouts'));
    }
};
