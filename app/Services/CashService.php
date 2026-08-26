<?php

namespace App\Services;

use App\Models\Business;
use App\Models\CashMovement;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class CashService
{
    /**
     * Expresión portable para agrupar por mes: las fechas se guardan como
     * 'YYYY-MM-DD', así que los primeros 7 caracteres son el mes.
     */
    private const MONTH_EXPR = 'substr(cast(occurred_at as varchar), 1, 7)';

    /**
     * Resuelve el rango de un corte; sin parámetros devuelve el mes en curso.
     *
     * @return array{0: string, 1: string}
     */
    public function range(?string $from, ?string $to): array
    {
        return [
            ($from ? Carbon::parse($from) : Carbon::today()->startOfMonth())->toDateString(),
            ($to ? Carbon::parse($to) : Carbon::today()->endOfMonth())->toDateString(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, ?int $userId = null): CashMovement
    {
        return CashMovement::create($this->attributes($data) + ['user_id' => $userId]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(CashMovement $movement, array $data): CashMovement
    {
        $movement->update($this->attributes($data));

        return $movement->fresh();
    }

    /**
     * Lista filtrada de movimientos. Devuelve el query para que el controlador
     * decida si pagina o exporta.
     *
     * @param  array<string, mixed>  $filters
     */
    public function query(array $filters): Builder
    {
        return CashMovement::query()
            ->with(['business', 'user'])
            ->when(! empty($filters['type']), fn ($q) => $q->where('type', $filters['type']))
            ->when(! empty($filters['source']), fn ($q) => $q->where('source', $filters['source']))
            ->when(! empty($filters['business_id']), fn ($q) => $q->where('business_id', $filters['business_id']))
            ->when(! empty($filters['from']), fn ($q) => $q->whereDate('occurred_at', '>=', $filters['from']))
            ->when(! empty($filters['to']), fn ($q) => $q->whereDate('occurred_at', '<=', $filters['to']))
            ->when(! empty($filters['search']), function ($q) use ($filters) {
                $term = '%'.$filters['search'].'%';
                $q->where(function ($q) use ($term) {
                    $q->where('concept', 'ilike', $term)
                        ->orWhereHas('business', fn ($b) => $b->where('name', 'ilike', $term));
                });
            })
            ->orderByDesc('occurred_at')
            ->orderByDesc('id');
    }

    /**
     * Totales de un conjunto de filtros, sin paginar.
     *
     * @param  array<string, mixed>  $filters
     * @return array{income: float, expense: float, balance: float, count: int}
     */
    public function totals(array $filters): array
    {
        $income = (float) $this->query($filters)->income()->sum('total');
        $expense = (float) $this->query($filters)->expense()->sum('total');

        return [
            'income' => round($income, 2),
            'expense' => round($expense, 2),
            'balance' => round($income - $expense, 2),
            'count' => $this->query($filters)->count(),
        ];
    }

    /**
     * Clientes a los que toca cobrar en la fecha dada, incluyendo los que ya
     * se pasaron de fecha y siguen sin pago nuevo.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function dueCharges(Carbon $date): Collection
    {
        return CashMovement::query()
            ->latestFeePerBusiness()
            ->whereNotNull('next_charge_date')
            ->whereDate('next_charge_date', '<=', $date->toDateString())
            ->with('business')
            ->orderBy('next_charge_date')
            ->get()
            ->filter(fn (CashMovement $m) => $m->business !== null)
            ->map(fn (CashMovement $m) => [
                'business_id' => $m->business_id,
                'business_name' => $m->business->name,
                'folio' => $m->business->folio,
                'phone' => $m->business->phone,
                'plan' => $m->business->plan,
                'payment_day' => $m->business->payment_day,
                'concept' => $m->concept,
                'amount' => (float) $m->total,
                'quantity' => $m->quantity,
                'unit_amount' => (float) $m->amount,
                'last_payment_at' => $m->occurred_at?->toDateString(),
                'next_charge_date' => $m->next_charge_date->toDateString(),
                'days_overdue' => max(0, (int) $m->next_charge_date->diffInDays($date, absolute: false)),
            ])
            ->values();
    }

    /**
     * Corte de un rango: totales, desglose por mes y clientes que más pagaron.
     *
     * @return array<string, mixed>
     */
    public function summary(string $from, string $to): array
    {
        $filters = ['from' => $from, 'to' => $to];
        $totals = $this->totals($filters);

        $incomeByMonth = $this->sumByMonth($from, $to, CashMovement::TYPE_INCOME);
        $expenseByMonth = $this->sumByMonth($from, $to, CashMovement::TYPE_EXPENSE);

        $months = collect($incomeByMonth->keys()->merge($expenseByMonth->keys())->unique())
            ->sort()
            ->values()
            ->map(fn (string $month) => [
                'month' => $month,
                'income' => round((float) $incomeByMonth->get($month, 0), 2),
                'expense' => round((float) $expenseByMonth->get($month, 0), 2),
                'balance' => round((float) $incomeByMonth->get($month, 0) - (float) $expenseByMonth->get($month, 0), 2),
            ]);

        return [
            'from' => $from,
            'to' => $to,
            'income' => $totals['income'],
            'expense' => $totals['expense'],
            'balance' => $totals['balance'],
            'count' => $totals['count'],
            'income_by_source' => [
                'fee' => round((float) CashMovement::query()->income()->between($from, $to)
                    ->where('source', CashMovement::SOURCE_FEE)->sum('total'), 2),
                'manual' => round((float) CashMovement::query()->income()->between($from, $to)
                    ->where('source', CashMovement::SOURCE_MANUAL)->sum('total'), 2),
            ],
            'months' => $months,
            'top_clients' => $this->topClients($from, $to),
        ];
    }

    /**
     * Filas planas listas para el CSV.
     *
     * @param  array<string, mixed>  $filters
     * @return Collection<int, array<int, string>>
     */
    public function exportRows(array $filters): Collection
    {
        return $this->query($filters)
            ->reorder('occurred_at')
            ->orderBy('id')
            ->get()
            ->map(fn (CashMovement $m) => [
                $m->occurred_at?->toDateString() ?? '',
                $m->type === CashMovement::TYPE_INCOME ? 'Entrada' : 'Salida',
                $m->source === CashMovement::SOURCE_FEE ? 'Cuota' : 'Manual',
                $m->business?->name ?? '',
                $m->business?->folio ?? '',
                $m->concept,
                (string) $m->quantity,
                number_format((float) $m->amount, 2, '.', ''),
                number_format((float) $m->total, 2, '.', ''),
                $m->next_charge_date?->toDateString() ?? '',
                $m->user?->name ?? '',
                (string) $m->notes,
            ]);
    }

    /**
     * @return array<int, string>
     */
    public function exportHeadings(): array
    {
        return [
            'Fecha', 'Tipo', 'Origen', 'Cliente', 'Folio', 'Concepto',
            'Cantidad', 'Monto unitario', 'Total', 'Próximo cobro', 'Registró', 'Notas',
        ];
    }

    /**
     * Normaliza los datos validados: el total se guarda calculado para poder
     * sumarlo en SQL sin recorrer los movimientos.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function attributes(array $data): array
    {
        $quantity = (int) $data['quantity'];
        $amount = round((float) $data['amount'], 2);

        return [
            'type' => $data['type'],
            'source' => $data['source'],
            'business_id' => $data['business_id'] ?? null,
            'concept' => $data['concept'],
            'quantity' => $quantity,
            'amount' => $amount,
            'total' => round($quantity * $amount, 2),
            'occurred_at' => $data['occurred_at'],
            'next_charge_date' => $data['next_charge_date'] ?? null,
            'notes' => $data['notes'] ?? null,
        ];
    }

    /**
     * @return Collection<string, float>
     */
    private function sumByMonth(string $from, string $to, string $type): Collection
    {
        return CashMovement::query()
            ->where('type', $type)
            ->between($from, $to)
            ->selectRaw(self::MONTH_EXPR.' as month, sum(total) as total')
            ->groupByRaw(self::MONTH_EXPR)
            ->pluck('total', 'month');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function topClients(string $from, string $to): array
    {
        $rows = CashMovement::query()
            ->income()
            ->between($from, $to)
            ->whereNotNull('business_id')
            ->selectRaw('business_id, sum(total) as total, count(*) as payments')
            ->groupBy('business_id')
            ->orderByDesc('total')
            ->limit(10)
            ->get();

        $names = Business::whereIn('id', $rows->pluck('business_id'))->pluck('name', 'id');

        return $rows->map(fn ($row) => [
            'business_id' => $row->business_id,
            'business_name' => $names->get($row->business_id, 'Cliente eliminado'),
            'total' => round((float) $row->total, 2),
            'payments' => (int) $row->payments,
        ])->all();
    }
}
