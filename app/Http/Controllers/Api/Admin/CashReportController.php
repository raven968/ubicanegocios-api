<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Services\CashService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class CashReportController extends Controller
{
    public function __construct(private readonly CashService $cash) {}

    /**
     * Cobros que tocan en la fecha dada (hoy por defecto) más los vencidos.
     * Alimenta la notificación del panel y la hoja de cobro.
     */
    public function due(Request $request)
    {
        $date = $request->filled('date')
            ? Carbon::parse($request->string('date')->toString())
            : Carbon::today();

        $charges = $this->cash->dueCharges($date);

        return response()->json([
            'date' => $date->toDateString(),
            'count' => $charges->count(),
            'total' => round((float) $charges->sum('amount'), 2),
            'overdue_count' => $charges->where('days_overdue', '>', 0)->count(),
            'data' => $charges,
        ]);
    }

    /**
     * Corte de caja de un rango; por defecto el mes en curso.
     */
    public function summary(Request $request)
    {
        [$from, $to] = $this->cash->range($request->input('from'), $request->input('to'));

        return response()->json($this->cash->summary($from, $to));
    }

    /**
     * Descarga de los movimientos del rango en CSV (abre directo en Excel).
     */
    public function export(Request $request)
    {
        [$from, $to] = $this->cash->range($request->input('from'), $request->input('to'));

        $filters = $request->only(['type', 'source', 'business_id', 'search'])
            + ['from' => $from, 'to' => $to];

        $rows = $this->cash->exportRows($filters);
        $headings = $this->cash->exportHeadings();
        $totals = $this->cash->totals($filters);

        return response()->streamDownload(function () use ($rows, $headings, $totals) {
            $out = fopen('php://output', 'w');
            // BOM para que Excel respete los acentos.
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, $headings);
            foreach ($rows as $row) {
                fputcsv($out, $row);
            }
            fputcsv($out, []);
            fputcsv($out, ['Entradas', number_format($totals['income'], 2, '.', '')]);
            fputcsv($out, ['Salidas', number_format($totals['expense'], 2, '.', '')]);
            fputcsv($out, ['Balance', number_format($totals['balance'], 2, '.', '')]);
            fclose($out);
        }, "cobranza_{$from}_a_{$to}.csv", [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
