<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('listings', function (Blueprint $table) {
            // Seller's minimum raise for ascending auctions. Enforced by this app:
            // the engine has no such setting.
            $table->unsignedBigInteger('bid_step')->nullable()->after('min_bid_price');
        });
    }

    public function down(): void
    {
        Schema::table('listings', function (Blueprint $table) {
            $table->dropColumn('bid_step');
        });
    }
};
