<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table(config('laranail.authkit.two_factor.table', 'users'), function (Blueprint $table): void {
            $table->enum('two_factor_method', ['none', 'totp'])->default('none');
            $table->text('two_factor_secret')->nullable();
            $table->text('two_factor_recovery_codes')->nullable();
            $table->timestamp('two_factor_confirmed_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table(config('laranail.authkit.two_factor.table', 'users'), function (Blueprint $table): void {
            $table->dropColumn([
                'two_factor_method', 'two_factor_secret', 'two_factor_recovery_codes',
                'two_factor_confirmed_at',
            ]);
        });
    }
};
