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
        Schema::table('purchase_entries', function (Blueprint $table) {
            if (!Schema::hasColumn('purchase_entries', 'from_receipt_note')) {
                $table->boolean('from_receipt_note')->default(false)->after('note');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('purchase_entries', function (Blueprint $table) {
            if (Schema::hasColumn('purchase_entries', 'from_receipt_note')) {
                $table->dropColumn('from_receipt_note');
            }
        });
    }
};