<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\BusinessRequest;
use App\Http\Resources\BusinessResource;
use App\Models\Business;
use App\Services\BusinessService;
use Illuminate\Http\Request;

class BusinessController extends Controller
{
    public function __construct(private readonly BusinessService $businesses) {}

    /**
     * List all businesses with optional search and filter by state.
     */
    public function index(Request $request)
    {
        $businesses = $this->businesses
            ->query($request->only(['search', 'active']))
            ->paginate(15)
            ->withQueryString();

        return BusinessResource::collection($businesses);
    }

    /**
     * Descarga del listado filtrado en CSV (abre directo en Excel).
     */
    public function export(Request $request)
    {
        $filters = $request->only(['search', 'active']);
        $rows = $this->businesses->exportRows($filters);
        $headings = $this->businesses->exportHeadings();

        return response()->streamDownload(function () use ($rows, $headings) {
            $out = fopen('php://output', 'w');
            // BOM para que Excel respete los acentos.
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, $headings);
            foreach ($rows as $row) {
                fputcsv($out, $row);
            }
            fclose($out);
        }, 'negocios_'.now()->toDateString().'.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function store(BusinessRequest $request)
    {
        $business = $this->businesses->create($request->validated());

        return new BusinessResource($business->load(['images', 'videos', 'categories', 'subcategories', 'zones']));
    }

    public function show(Business $business)
    {
        return new BusinessResource(
            $business->load(['images', 'videos', 'categories', 'subcategories', 'zones', 'reviews'])
        );
    }

    public function update(BusinessRequest $request, Business $business)
    {
        $business = $this->businesses->update($business, $request->validated());

        return new BusinessResource($business->load(['images', 'videos', 'categories', 'subcategories', 'zones']));
    }

    public function destroy(Business $business)
    {
        $business->delete();

        return response()->noContent();
    }
}
