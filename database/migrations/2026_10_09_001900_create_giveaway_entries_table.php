<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
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
    }
};
