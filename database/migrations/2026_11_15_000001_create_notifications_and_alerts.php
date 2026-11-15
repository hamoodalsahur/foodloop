<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * In-app notifications (the bell), deal alerts, "Notify me next time" for a
 * shop, and the admin's "Ask for info" question to a shop waiting for approval.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Laravel's standard table for database notifications.
        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->morphs('notifiable');
            $table->text('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table) {
            // "Turn on deal alerts": a notification for every new deal.
            $table->boolean('deal_alerts')->default(false)->after('blocked_until');
        });

        // "Notify me next time": a customer follows one shop.
        Schema::create('shop_follows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('seller_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'seller_id']);
        });

        Schema::table('sellers', function (Blueprint $table) {
            // What the admin asked this shop before approving it; empty once the shop has answered.
            $table->text('info_request')->nullable()->after('approved_at');
            $table->timestamp('info_requested_at')->nullable()->after('info_request');
        });
    }

    public function down(): void
    {
        Schema::table('sellers', function (Blueprint $table) {
            $table->dropColumn(['info_request', 'info_requested_at']);
        });

        Schema::dropIfExists('shop_follows');

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('deal_alerts');
        });

        Schema::dropIfExists('notifications');
    }
};
