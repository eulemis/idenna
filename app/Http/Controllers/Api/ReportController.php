<?php

namespace App\Http\Controllers\Api;

use App\Exports\NnaExport;
use App\Http\Controllers\Controller;
use App\Models\NnaRegistration;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ReportController extends Controller
{
    public function export(Request $request): BinaryFileResponse
    {
        $this->authorize('reports.export');

        $format = $request->string('format', 'xlsx')->toString();
        $operativoId = $request->integer('operativo_id') ?: null;
        $filename = 'registros-nna-'.now()->format('Y-m-d');

        if ($format === 'csv') {
            return Excel::download(new NnaExport($operativoId), "{$filename}.csv", \Maatwebsite\Excel\Excel::CSV);
        }

        if ($format === 'pdf') {
            $records = NnaRegistration::query()
                ->when($operativoId, fn ($q) => $q->where('operativo_id', $operativoId))
                ->orderByDesc('registered_at')
                ->limit(500)
                ->get();

            $pdf = Pdf::loadView('reports.nna-pdf', [
                'records' => $records,
                'generatedAt' => now()->format('d/m/Y H:i'),
            ])->setPaper('a4', 'landscape');

            return $pdf->download("{$filename}.pdf");
        }

        return Excel::download(new NnaExport($operativoId), "{$filename}.xlsx");
    }
}
