<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('listings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('city_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->longText('description')->nullable();
            $table->string('condition', 20);

            // Auction parameters as sent to the engine when the listing was created.
            $table->unsignedBigInteger('auction_id')->nullable()->unique();
            $table->unsignedTinyInteger('auction_type');
            $table->unsignedBigInteger('start_price');
            $table->unsignedBigInteger('min_bid_price')->nullable();
            $table->dateTime('start_time')->nullable();
            $table->dateTime('end_time')->nullable();
            $table->boolean('auto_extend')->default(false);
            $table->unsignedInteger('down_time')->nullable();
            $table->unsignedBigInteger('down_price')->nullable();
            $table->unsignedBigInteger('finish_price')->nullable();

            // Snapshot of the engine state, refreshed by ListingSyncService.
            $table->unsignedTinyInteger('auction_status')->default(0)->index();
            $table->unsignedBigInteger('current_price')->default(0);
            $table->unsignedInteger('bid_count')->default(0);
            $table->dateTime('ends_at')->nullable()->index();
            $table->foreignId('winner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedBigInteger('winning_price')->nullable();
            $table->dateTime('synced_at')->nullable();

            $table->timestamps();
        });

        Schema::create('listing_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('listing_id')->constrained()->cascadeOnDelete();
            $table->string('path');
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();
        });

        Schema::create('listing_tag', function (Blueprint $table) {
            $table->foreignId('listing_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tag_id')->constrained()->cascadeOnDelete();
            $table->primary(['listing_id', 'tag_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('listing_tag');
        Schema::dropIfExists('listing_images');
        Schema::dropIfExists('listings');
    }
};
