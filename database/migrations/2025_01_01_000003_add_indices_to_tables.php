<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->unique('name');
        });

        Schema::table('vending_machines', function (Blueprint $table) {
            // Composite index for the "pick least-used idle machine" query:
            // WHERE status = ? ORDER BY usage_count
            $table->index(['status', 'usage_count']);
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropUnique(['name']);
        });

        Schema::table('vending_machines', function (Blueprint $table) {
            $table->dropIndex(['status', 'usage_count']);
        });
    }
};
