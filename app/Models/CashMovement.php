<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CashMovement extends Model
{
    public const TYPE_INCOME = 'income';

    public const TYPE_EXPENSE = 'expense';

    /** Captura libre: monto, concepto y cantidad los escribe el usuario. */
    public const SOURCE_MANUAL = 'manual';

    /** Entrada ligada a la cuota de un negocio; arrastra próxima fecha de cobro. */
    public const SOURCE_FEE = 'fee';

    public const TYPES = [self::TYPE_INCOME, self::TYPE_EXPENSE];

    public const SOURCES = [self::SOURCE_MANUAL, self::SOURCE_FEE];

    protected $fillable = [
        'type', 'source', 'business_id', 'user_id', 'concept',
        'quantity', 'amount', 'total', 'occurred_at', 'next_charge_date', 'notes',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'amount' => 'decimal:2',
        'total' => 'decimal:2',
        'occurred_at' => 'date',
        'next_charge_date' => 'date',
    ];

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * whereDate (y no whereBetween) porque el driver puede guardar la fecha con
     * hora; así el límite superior del rango no se pierde.
     */
    public function scopeBetween(Builder $query, string $from, string $to): Builder
    {
        return $query
            ->whereDate('occurred_at', '>=', $from)
            ->whereDate('occurred_at', '<=', $to);
    }

    public function scopeIncome(Builder $query): Builder
    {
        return $query->where('type', self::TYPE_INCOME);
    }

    public function scopeExpense(Builder $query): Builder
    {
        return $query->where('type', self::TYPE_EXPENSE);
    }

    /**
     * Restringe a la última cuota registrada de cada negocio, que es la que
     * manda la próxima fecha de cobro: al registrar un pago nuevo, el anterior
     * deja de contar.
     */
    public function scopeLatestFeePerBusiness(Builder $query): Builder
    {
        return $query
            ->where('source', self::SOURCE_FEE)
            ->whereNotNull('business_id')
            ->whereIn('id', function ($sub) {
                $sub->selectRaw('max(id)')
                    ->from('cash_movements')
                    ->where('source', self::SOURCE_FEE)
                    ->whereNotNull('business_id')
                    ->groupBy('business_id');
            });
    }
}
