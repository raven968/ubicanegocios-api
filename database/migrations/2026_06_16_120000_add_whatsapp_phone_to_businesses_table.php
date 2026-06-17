<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Stores which phone column is the WhatsApp number: 'phone', 'phone2' or null.
     */
    public function up(): void
    {
        Schema::table('businesses', function (Blueprint $table) {
            $table->string('whatsapp_phone', 10)->nullable()->after('phone2');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('businesses', function (Blueprint $table) {
            $table->dropColumn('whatsapp_phone');
        });
    }
};
