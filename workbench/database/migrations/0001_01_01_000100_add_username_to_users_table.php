<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

/**
 * The username column lives in the workbench, not in the package.
 *
 * AuthKit resolves whatever user model the application configures and does not own the users
 * table, so it ships no migration for it. The application adds the column and its uniqueness
 * constraint. This migration is the test harness standing in for that application, which is also
 * what keeps the package honest: if the package ever started depending on a column it does not
 * create, the suite would be the only place that column existed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('username')->nullable()->unique()->after('name');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropUnique(['username']);
            $table->dropColumn('username');
        });
    }
};
