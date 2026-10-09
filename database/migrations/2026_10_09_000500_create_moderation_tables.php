<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('blocked_ips', function (Blueprint $table) {
            $table->id();
            $table->string('address', 45)->unique();
            $table->string('description')->nullable();
            $table->timestamps();
        });

        Schema::create('blocked_email_domains', function (Blueprint $table) {
            $table->id();
            $table->string('domain')->unique();
            $table->string('description')->nullable();
            $table->timestamps();
        });

        Schema::create('unlock_requests', function (Blueprint $table) {
            $table->id();
            $table->string('email');
            $table->text('description')->nullable();
            $table->timestamp('handled_at')->nullable();
            $table->timestamps();
        });

        Schema::create('settings', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->json('value')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
        Schema::dropIfExists('unlock_requests');
        Schema::dropIfExists('blocked_email_domains');
        Schema::dropIfExists('blocked_ips');
    }
};
