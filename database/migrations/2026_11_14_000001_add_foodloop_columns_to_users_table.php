<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // customer, seller or admin (App\Enums\UserRole). Everyone starts as a customer.
            $table->string('role', 20)->default('customer')->index()->after('password');
            // No-show strikes; at config('foodloop.strikes_to_block') the user is blocked.
            $table->unsignedSmallInteger('no_show_strikes')->default(0)->after('role');
            // While this is in the future the user cannot reserve.
            $table->dateTime('blocked_until')->nullable()->after('no_show_strikes');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['role']);
            $table->dropColumn(['role', 'no_show_strikes', 'blocked_until']);
        });
    }
};
