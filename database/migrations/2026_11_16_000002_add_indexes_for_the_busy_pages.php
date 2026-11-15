<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Indexes for what the busiest pages filter and sort by, so they stay quick
 * as the number of orders grows. Only adds indexes: run `php artisan migrate`,
 * no data changes. Works the same on SQLite and MySQL.
 *
 * Already there before this (not repeated): reservations (listing_id, status)
 * and (status, expires_at); listings (status, pickup_end); the status columns
 * of sellers and reports; users.role.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            // A customer's orders and impact pages; "how many of this listing do I already hold".
            $table->index(['customer_id', 'status'], 'reservations_customer_status_index');
            // "Meals rescued this month", sales this week, the collected lists.
            $table->index(['status', 'collected_at'], 'reservations_status_collected_at_index');
            // Admin → Orders: one tab, newest first…
            $table->index(['status', 'created_at'], 'reservations_status_created_at_index');
            // …and the All tab, newest first.
            $table->index('created_at', 'reservations_created_at_index');
        });

        Schema::table('listings', function (Blueprint $table) {
            // A shop's own listings by status and pickup time (seller dashboard, listings, leftovers).
            $table->index(['seller_id', 'status', 'pickup_end'], 'listings_seller_status_pickup_end_index');
            // The deals page and the map: live listings whose pickup window hasn't opened yet.
            $table->index(['status', 'pickup_start'], 'listings_status_pickup_start_index');
        });
    }

    public function down(): void
    {
        // MySQL uses an index that starts with customer_id / seller_id for the
        // foreign key, and may have dropped its own one when the wider index
        // arrived. Put a plain one back first so the wider one can go.
        Schema::table('reservations', function (Blueprint $table) {
            $table->index('customer_id', 'reservations_customer_id_plain_index');
        });
        Schema::table('listings', function (Blueprint $table) {
            $table->index('seller_id', 'listings_seller_id_plain_index');
        });

        Schema::table('reservations', function (Blueprint $table) {
            $table->dropIndex('reservations_customer_status_index');
            $table->dropIndex('reservations_status_collected_at_index');
            $table->dropIndex('reservations_status_created_at_index');
            $table->dropIndex('reservations_created_at_index');
        });

        Schema::table('listings', function (Blueprint $table) {
            $table->dropIndex('listings_seller_status_pickup_end_index');
            $table->dropIndex('listings_status_pickup_start_index');
        });
    }
};
