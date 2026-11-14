<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reservations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('listing_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedSmallInteger('quantity');
            $table->unsignedInteger('total_sen');
            // pay_now, pay_at_pickup (App\Enums\PaymentMethod). Simulated only.
            $table->string('payment_method', 20);
            // simulated_paid, unpaid, refunded (App\Enums\PaymentStatus)
            $table->string('payment_status', 20);
            // 4 digits the customer shows at pickup.
            $table->char('pickup_code', 4);
            // reserved, collected, cancelled, no_show (App\Enums\ReservationStatus)
            $table->string('status', 20)->default('reserved');
            // pickup_end + grace minutes. After this an uncollected reservation is a no-show.
            $table->dateTime('expires_at');
            $table->dateTime('collected_at')->nullable();
            $table->dateTime('cancelled_at')->nullable();
            // Cancelled after the free-cancel cutoff (counts as a strike).
            $table->boolean('late_cancel')->default(false);
            $table->timestamps();

            $table->index(['listing_id', 'status']);
            $table->index(['status', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reservations');
    }
};
