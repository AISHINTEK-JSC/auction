<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Where a member wants what they buy delivered. One per member for
        // now: drop the unique index to let a member keep several.
        Schema::create('shipping_addresses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('recipient_name');
            $table->string('phone', 20);
            $table->foreignId('city_id')->nullable()->constrained()->nullOnDelete();
            // House number, street, ward, district.
            $table->string('address');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipping_addresses');
    }
};
