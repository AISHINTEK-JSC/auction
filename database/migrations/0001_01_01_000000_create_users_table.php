<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('phone', 20)->nullable();
            $table->string('address')->nullable();
            // male, female or other (App\Enums\Gender).
            $table->string('gender', 10)->nullable();
            $table->date('birthday')->nullable();
            $table->string('avatar_path')->nullable();
            // user, moderator or admin (App\Enums\UserRole).
            $table->string('role', 20)->default('user')->index();
            // Permanent number a member takes part in giveaways with.
            $table->string('member_code', 8)->nullable()->unique();
            $table->timestamp('blocked_at')->nullable();
            $table->string('block_reason')->nullable();
            // Set when a winner gave up auction results too often.
            $table->dateTime('bid_banned_until')->nullable();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->text('two_factor_secret')->nullable();
            $table->text('two_factor_recovery_codes')->nullable();
            $table->timestamp('two_factor_confirmed_at')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
