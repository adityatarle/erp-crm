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
        Schema::table('payments', function (Blueprint $table) {
            $table->string('type')->nullable()->after('bank_name'); // Add type column after bank_name
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
       Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn('type');
        });
    }
};
