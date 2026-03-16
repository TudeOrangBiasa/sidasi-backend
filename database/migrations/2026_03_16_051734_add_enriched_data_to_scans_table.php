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
            $table->string('quantity')->nullable()->after('image_url');
            $table->string('categories')->nullable()->after('quantity');
            $table->string('nutriscore_grade')->nullable()->after('categories');
            $table->string('packaging_shape')->nullable()->after('nutriscore_grade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('scans', function (Blueprint $table) {
            $table->dropColumn(['quantity', 'categories', 'nutriscore_grade', 'packaging_shape']);
        });
    }
};
