<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(table: 'passkeys', callback: function (Blueprint $table): void {
            $table->id();
            $table->morphs(name: 'passkeyable');
            $table->string(column: 'name');
            $table->string(column: 'credential_id')->unique();
            $table->json(column: 'credential');
            $table->timestamp(column: 'last_used_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(table: 'passkeys');
    }
};
