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
        Schema::create('cash_movements', function (Blueprint $table) {
            $table->id();
            // income | expense
            $table->string('type', 20);
            // manual (captura libre) | fee (cuota de un negocio)
            $table->string('source', 20)->default('manual');
            $table->foreignId('business_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('concept');
            $table->unsignedInteger('quantity')->default(1);
            // Monto unitario; total = quantity * amount, guardado para poder sumar en SQL.
            $table->decimal('amount', 12, 2);
            $table->decimal('total', 14, 2);
            $table->date('occurred_at');
            // Solo en las entradas por cuota: cuándo se le vuelve a cobrar al negocio.
            $table->date('next_charge_date')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['occurred_at', 'type']);
            $table->index(['business_id', 'next_charge_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cash_movements');
    }
};
