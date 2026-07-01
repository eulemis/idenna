<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\DashboardService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DashboardController extends Controller
{
    public function __construct(private readonly DashboardService $dashboardService) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('reports.view');

        return response()->json([
            'data' => $this->dashboardService->getStats($this->filtersFromRequest($request)),
        ]);
    }

    public function export(Request $request): StreamedResponse|\Illuminate\Http\Response
    {
        $this->authorize('reports.export');

        $format = $request->string('format', 'xlsx')->toString();
        if (! in_array($format, ['xlsx', 'csv', 'pdf'], true)) {
            abort(422, 'Formato no válido.');
        }

        $stats = $this->dashboardService->getStats($this->filtersFromRequest($request));
        $filename = 'panel-nna-'.now()->format('Y-m-d');

        if ($format === 'pdf') {
            $pdf = Pdf::loadView('reports.dashboard-pdf', [
                'stats' => $stats,
                'generatedAt' => now()->format('d/m/Y H:i'),
            ])->setPaper('a4', 'landscape');

            return $pdf->download("{$filename}.pdf");
        }

        return response()->streamDownload(function () use ($stats) {
            $out = fopen('php://output', 'w');
            fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));

            fputcsv($out, ['Panel ejecutivo SIRP-NNA']);
            fputcsv($out, ['Generado', now()->format('d/m/Y H:i')]);
            fputcsv($out, []);

            fputcsv($out, ['KPI', 'Valor']);
            foreach ($stats['kpis'] as $key => $value) {
                fputcsv($out, [$key, $value]);
            }
            fputcsv($out, []);

            $sections = [
                'Por estado' => $stats['by_estado'] ?? [],
                'Por género' => $stats['by_gender'] ?? [],
                'Por grupo de edad' => array_map(fn ($r) => ['name' => $r['group'], 'total' => $r['total']], $stats['by_age_group'] ?? []),
                'Por lugar' => $stats['by_lugar'] ?? [],
                'Productividad (top 10)' => $stats['productivity_by_user'] ?? [],
                'Evolución 30 días' => array_map(fn ($r) => ['name' => $r['date'], 'total' => $r['total']], $stats['timeline'] ?? []),
            ];

            foreach ($sections as $title => $rows) {
                fputcsv($out, [$title]);
                fputcsv($out, ['Etiqueta', 'Total']);
                foreach ($rows as $row) {
                    fputcsv($out, [$row['name'] ?? $row['group'] ?? '—', $row['total'] ?? 0]);
                }
                fputcsv($out, []);
            }

            fclose($out);
        }, "{$filename}.csv", [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

  private function filtersFromRequest(Request $request): array
    {
        return array_filter([
            'operativo_id' => $request->integer('operativo_id') ?: null,
            'estado_id' => $request->integer('estado_id') ?: null,
            'from' => $request->string('from')->toString() ?: null,
            'to' => $request->string('to')->toString() ?: null,
        ], fn ($v) => $v !== null && $v !== '');
    }
}
