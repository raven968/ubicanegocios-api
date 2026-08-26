<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Billing data captured by the admin: alta del cliente, con quién se trata y
     * el día del mes en que toca cobrarle. Nunca se expone públicamente.
     */
    public function up(): void
    {
        Schema::table('businesses', function (Blueprint $table) {
            $table->date('joined_at')->nullable();
            $table->string('contact_name', 150)->nullable();
            $table->unsignedTinyInteger('payment_day')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('businesses', function (Blueprint $table) {
            $table->dropColumn(['joined_at', 'contact_name', 'payment_day']);
        });
    }
};
