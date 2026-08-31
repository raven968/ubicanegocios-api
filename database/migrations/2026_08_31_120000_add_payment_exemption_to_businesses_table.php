<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Clientes que por acuerdo no pagan (intercambio de publicidad, cortesía,
     * influencers) y el motivo. Quedan fuera de la hoja de cobro. Igual que el
     * resto de los datos de cobranza, nunca se exponen públicamente.
     */
    public function up(): void
    {
        Schema::table('businesses', function (Blueprint $table) {
            $table->boolean('payment_exempt')->default(false)->index();
            $table->text('billing_notes')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('businesses', function (Blueprint $table) {
            $table->dropColumn(['payment_exempt', 'billing_notes']);
        });
    }
};
