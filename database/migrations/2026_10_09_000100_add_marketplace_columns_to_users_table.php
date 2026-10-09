<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('phone', 20)->nullable()->after('email');
            $table->string('address')->nullable()->after('phone');
            $table->string('avatar_path')->nullable()->after('address');
            $table->boolean('is_admin')->default(false)->after('avatar_path');
            $table->timestamp('blocked_at')->nullable()->after('is_admin');
            $table->string('block_reason')->nullable()->after('blocked_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['phone', 'address', 'avatar_path', 'is_admin', 'blocked_at', 'block_reason']);
        });
    }
};
