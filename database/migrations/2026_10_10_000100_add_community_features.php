<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role', 20)->default('user')->index()->after('avatar_path');
            // Permanent number a member takes part in giveaways with.
            $table->string('member_code', 8)->nullable()->unique()->after('role');
        });

        DB::table('users')->where('is_admin', true)->update(['role' => 'admin']);

        foreach (DB::table('users')->orderBy('id')->pluck('id') as $id) {
            do {
                $code = str_pad((string) random_int(0, 999_999), 6, '0', STR_PAD_LEFT);
            } while (DB::table('users')->where('member_code', $code)->exists());

            DB::table('users')->where('id', $id)->update(['member_code' => $code]);
        }

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('is_admin');
        });

        Schema::table('listings', function (Blueprint $table) {
            $table->string('moderation_status', 20)->default('approved')->index()->after('condition');
            $table->string('moderation_note')->nullable()->after('moderation_status');
            $table->foreignId('relisted_from_id')->nullable()->after('moderation_note')->constrained('listings')->nullOnDelete();
            // Current highest bidder according to the engine (a user id, no constraint:
            // the engine owns this value).
            $table->unsignedBigInteger('leader_id')->nullable()->after('bid_count');
            $table->dateTime('closed_notified_at')->nullable()->after('synced_at');
        });

        // Auctions that ended before notifications existed must not be announced now.
        DB::table('listings')->whereNotIn('auction_status', [0, 1])->update(['closed_notified_at' => now()]);

        Schema::create('listing_watchers', function (Blueprint $table) {
            $table->foreignId('listing_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamp('created_at')->nullable();
            $table->primary(['listing_id', 'user_id']);
        });

        // Who has bid on what: the engine can only tell us this per auction.
        Schema::create('listing_bidders', function (Blueprint $table) {
            $table->foreignId('listing_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('last_bid_price');
            $table->timestamps();
            $table->primary(['listing_id', 'user_id']);
        });

        Schema::create('reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('listing_id')->constrained()->cascadeOnDelete();
            $table->foreignId('reviewer_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('reviewee_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedTinyInteger('rating');
            $table->text('comment')->nullable();
            $table->timestamps();
            $table->unique(['listing_id', 'reviewer_id']);
        });

        Schema::create('listing_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('listing_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('reason', 30);
            $table->text('note')->nullable();
            $table->timestamp('handled_at')->nullable();
            $table->foreignId('handled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['listing_id', 'user_id']);
        });

        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->morphs('notifiable');
            $table->text('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });

        Schema::create('giveaways', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('prize');
            $table->text('description')->nullable();
            $table->string('image_path')->nullable();
            $table->unsignedInteger('winner_count')->default(1);
            $table->dateTime('ends_at');
            $table->dateTime('drawn_at')->nullable();
            $table->timestamps();
        });

        Schema::create('giveaway_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('giveaway_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->boolean('is_winner')->default(false);
            $table->timestamps();
            // One entry per member per giveaway.
            $table->unique(['giveaway_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('giveaway_entries');
        Schema::dropIfExists('giveaways');
        Schema::dropIfExists('notifications');
        Schema::dropIfExists('listing_reports');
        Schema::dropIfExists('reviews');
        Schema::dropIfExists('listing_bidders');
        Schema::dropIfExists('listing_watchers');

        Schema::table('listings', function (Blueprint $table) {
            $table->dropConstrainedForeignId('relisted_from_id');
            $table->dropColumn(['moderation_status', 'moderation_note', 'leader_id', 'closed_notified_at']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_admin')->default(false);
        });

        DB::table('users')->where('role', 'admin')->update(['is_admin' => true]);

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['role', 'member_code']);
        });
    }
};
