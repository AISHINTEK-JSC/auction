<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('listings', function (Blueprint $table) {
            // Set when the seller has been told the listing is about to expire.
            $table->dateTime('expiry_reminded_at')->nullable()->after('closed_notified_at');
        });

        // A search a member wants to hear about: every criterion is optional,
        // a new listing matches when it meets all that are set.
        Schema::create('saved_searches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('q', 100)->nullable();
            $table->foreignId('category_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('city_id')->nullable()->constrained()->cascadeOnDelete();
            // 1 ascending auction, 2 descending auction, 3 fixed price (as in the search filter).
            $table->unsignedTinyInteger('type')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('saved_searches');

        Schema::table('listings', function (Blueprint $table) {
            $table->dropColumn('expiry_reminded_at');
        });
    }
};
