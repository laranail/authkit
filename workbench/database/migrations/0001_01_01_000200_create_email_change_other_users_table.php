<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

// Test-only second account table for cross-table identity isolation. Create it during migration,
// before RefreshDatabase starts transactions: MySQL/MariaDB DDL implicitly commits active ones.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('email_change_other_users', function (Blueprint $table): void {
            $table->id();
            $table->string('email')->unique();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_change_other_users');
    }
};
