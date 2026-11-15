<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Who used the admin override. Empty for every normal pickup (the shop typed
 * the customer's code); filled with the admin's id when an admin marked the
 * order collected from Admin → Orders.
 *
 * Only adds a column: run `php artisan migrate`, nothing is wiped.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->foreignId('collected_by_admin_id')->nullable()->after('collected_at')
                ->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('collected_by_admin_id');
        });
    }
};
