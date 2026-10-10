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
            // 'auction' or 'fixed'. A fixed-price listing has no auction at all.
            $table->string('sale_type', 10)->default('auction')->index();
            // draft, pending, approved, rejected or hidden (App\Enums\ModerationStatus).
            $table->string('moderation_status', 20)->default('approved')->index();
            $table->string('moderation_note')->nullable();
            $table->foreignId('relisted_from_id')->nullable()->constrained('listings')->nullOnDelete();

            // Auction parameters as sent to the engine when the listing was created.
            // Empty for a fixed-price listing, apart from the price.
            $table->unsignedBigInteger('auction_id')->nullable()->unique();
            $table->unsignedTinyInteger('auction_type')->nullable();
            $table->unsignedBigInteger('start_price');
            // "Giá thoả thuận": a fixed-price listing without a stated price.
            $table->boolean('is_negotiable')->default(false);
            $table->unsignedBigInteger('min_bid_price')->nullable();
            // Seller's minimum raise for ascending auctions. Enforced by this app:
            // the engine has no such setting.
            $table->unsignedBigInteger('bid_step')->nullable();
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
            $table->unsignedBigInteger('view_count')->default(0)->index();
            // Current highest bidder according to the engine (a user id, no constraint:
            // the engine owns this value).
            $table->unsignedBigInteger('leader_id')->nullable();
            $table->dateTime('ends_at')->nullable()->index();
            $table->foreignId('winner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedBigInteger('winning_price')->nullable();
            $table->dateTime('synced_at')->nullable();
            // Set once the parties have been told the auction is over.
            $table->dateTime('closed_notified_at')->nullable();
            // Set when the seller has been told the listing is about to expire.
            $table->dateTime('expiry_reminded_at')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('listings');
    }
};
