<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Lives in the host app's real DB connection (not the per-session demo
     * SQLite copy) — tracks which demo sessions exist so cleanup can find
     * and expire them. Recipe application itself is Phase 2 scope.
     */
    public function up(): void
    {
        Schema::create('nawate_demo_sessions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('recipe');
            $table->string('demo_db_path');
            $table->timestamp('expires_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nawate_demo_sessions');
    }
};
