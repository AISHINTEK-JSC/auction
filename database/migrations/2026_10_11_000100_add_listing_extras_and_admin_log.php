<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('listings', function (Blueprint $table) {
            // "Giá thoả thuận": a fixed-price listing without a stated price.
            $table->boolean('is_negotiable')->default(false)->after('start_price');
            $table->unsignedBigInteger('view_count')->default(0)->index()->after('bid_count');
        });

        // Fixed-price listings posted before they could expire get the usual lifetime from now.
        DB::table('listings')
            ->where('sale_type', 'fixed')
            ->whereIn('auction_status', [0, 1])
            ->whereNull('ends_at')
            ->update(['ends_at' => now()->addDays((int) config('marketplace.fixed_listing_days', 30))]);

        Schema::table('users', function (Blueprint $table) {
            $table->dateTime('bid_banned_until')->nullable()->after('block_reason');
        });

        // One row each time a winner gave up an auction result.
        Schema::create('forfeits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('listing_id')->constrained()->cascadeOnDelete();
            // The winner themselves, or the seller reporting that the winner did not buy.
            $table->foreignId('declared_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // What staff did in the admin panel, and who did it.
        Schema::create('admin_actions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action', 60)->index();
            $table->nullableMorphs('subject');
            $table->text('summary');
            $table->timestamp('created_at')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_actions');
        Schema::dropIfExists('forfeits');

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('bid_banned_until');
        });

        Schema::table('listings', function (Blueprint $table) {
            $table->dropColumn(['is_negotiable', 'view_count']);
        });
    }
};
