<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('scans', function (Blueprint $table) {
            $table->string('scan_ip')->nullable()->after('image_url');
            $table->string('scan_city')->nullable()->after('scan_ip');
            $table->string('scan_region')->nullable()->after('scan_city');
            $table->string('scan_country')->nullable()->after('scan_region');
            $table->decimal('scan_lat', 10, 7)->nullable()->after('scan_country');
            $table->decimal('scan_lon', 10, 7)->nullable()->after('scan_lat');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('scans', function (Blueprint $table) {
            $table->dropColumn(['scan_ip', 'scan_city', 'scan_region', 'scan_country', 'scan_lat', 'scan_lon']);
        });
    }
};
