<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('listings', function (Blueprint $table) {
            // 'auction' or 'fixed'. A fixed-price listing has no auction at all.
            $table->string('sale_type', 10)->default('auction')->index()->after('condition');
            $table->unsignedTinyInteger('auction_type')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('listings', function (Blueprint $table) {
            $table->dropColumn('sale_type');
        });
    }
};
