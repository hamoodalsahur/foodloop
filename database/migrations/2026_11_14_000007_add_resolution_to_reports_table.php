<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reports', function (Blueprint $table) {
            // What the admin did when they acted on the report:
            // listing_hidden or seller_warned (App\Enums\ReportResolution). Empty while open or when dismissed.
            $table->string('resolution', 30)->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('reports', function (Blueprint $table) {
            $table->dropColumn('resolution');
        });
    }
};
