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
            $table->foreignId('seller_id')->constrained()->cascadeOnDelete();
            // regular, flash, bundle (App\Enums\ListingType)
            $table->string('type', 20)->default('regular');
            $table->string('title');
            $table->text('description')->nullable();
            // bakery, meals, kuih, drinks, groceries (App\Enums\ListingCategory)
            $table->string('category', 20);
            $table->string('photo_path')->nullable();
            // Money is whole sen: RM8 = 800.
            $table->unsignedInteger('original_price_sen');
            $table->unsignedInteger('price_sen');
            $table->unsignedInteger('quantity_total');
            // Only the reservation service changes this (Stage 2).
            $table->unsignedInteger('quantity_available');
            $table->dateTime('pickup_start');
            $table->dateTime('pickup_end');
            // certified, muslim_owned, not_halal (App\Enums\HalalStatus). Required.
            $table->string('halal_status', 20);
            $table->json('allergens')->nullable();
            // Offer what's left to the partner food bank when the listing closes.
            $table->boolean('offer_leftovers')->default(false);
            // draft, live, paused, sold_out, closed (App\Enums\ListingStatus)
            $table->string('status', 20)->default('draft');
            $table->dateTime('published_at')->nullable();
            $table->dateTime('closed_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'pickup_end']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('listings');
    }
};
