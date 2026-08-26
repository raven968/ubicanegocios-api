<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CashMovementRequest;
use App\Http\Resources\CashMovementResource;
use App\Models\CashMovement;
use App\Services\CashService;
use Illuminate\Http\Request;

class CashMovementController extends Controller
{
    public function __construct(private readonly CashService $cash) {}

    /**
     * Movimientos filtrados por tipo, origen, cliente, rango de fechas o texto.
     * La respuesta incluye los totales del filtro completo, no solo de la página.
     */
    public function index(Request $request)
    {
        $filters = $request->only(['type', 'source', 'business_id', 'from', 'to', 'search']);

        $movements = $this->cash->query($filters)
            ->paginate($request->integer('per_page', 20))
            ->withQueryString();

        return CashMovementResource::collection($movements)
            ->additional(['totals' => $this->cash->totals($filters)]);
    }

    public function store(CashMovementRequest $request)
    {
        $movement = $this->cash->create($request->validated(), $request->user()?->id);

        return new CashMovementResource($movement->load(['business', 'user']));
    }

    public function update(CashMovementRequest $request, CashMovement $movement)
    {
        $movement = $this->cash->update($movement, $request->validated());

        return new CashMovementResource($movement->load(['business', 'user']));
    }

    public function destroy(CashMovement $movement)
    {
        $movement->delete();

        return response()->noContent();
    }
}
