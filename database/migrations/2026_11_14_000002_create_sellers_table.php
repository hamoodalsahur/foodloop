<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sellers', function (Blueprint $table) {
            $table->id();
            // One shop per seller account.
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('business_name');
            // Business registration number. Empty until the seller provides it.
            $table->string('ssm_no', 50)->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('address')->nullable();
            $table->decimal('lat', 10, 7)->nullable();
            $table->decimal('lng', 10, 7)->nullable();
            // certified, muslim_owned, not_halal (App\Enums\HalalStatus)
            $table->string('halal_claim', 20);
            // The uploaded halal certificate, if any.
            $table->string('halal_cert_path')->nullable();
            // pending, approved, rejected, suspended (App\Enums\SellerStatus)
            $table->string('status', 20)->default('pending')->index();
            $table->dateTime('approved_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sellers');
    }
};
