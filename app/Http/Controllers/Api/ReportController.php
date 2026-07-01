<?php

namespace App\Http\Controllers\Api;

use App\Exports\NnaExport;
use App\Http\Controllers\Controller;
use App\Jobs\ExportNnaReportJob;
use App\Models\NnaRegistration;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    private const ASYNC_THRESHOLD = 8000;

    private const PDF_SYNC_MAX = 2000;

    public function export(Request $request): BinaryFileResponse|StreamedResponse|JsonResponse
    {
        $this->authorize('reports.export');

        $format = $request->string('format', 'xlsx')->toString();
        if (! in_array($format, ['xlsx', 'csv', 'pdf'], true)) {
            return response()->json(['message' => 'Formato no válido.'], 422);
        }

        $operativoId = $request->integer('operativo_id') ?: null;
        $query = $this->baseQuery($operativoId);
        $total = (clone $query)->count();
        $filename = 'registros-nna-'.now()->format('Y-m-d');

        if ($format === 'csv') {
            return $this->streamCsv($query, "{$filename}.csv");
        }

        if ($format === 'pdf') {
            if ($total > self::PDF_SYNC_MAX) {
                return $this->dispatchAsyncExport($request, $format, $operativoId, $total);
            }

            return $this->downloadPdf($query, "{$filename}.pdf", $total);
        }

        if ($total > self::ASYNC_THRESHOLD) {
            return $this->dispatchAsyncExport($request, $format, $operativoId, $total);
        }

        @ini_set('memory_limit', '512M');
        set_time_limit(600);

        return Excel::download(new NnaExport($operativoId), "{$filename}.xlsx");
    }

    public function exportStatus(Request $request, string $token): JsonResponse
    {
        $this->authorize('reports.export');

        $cacheKey = "export:nna:{$token}";
        $data = Cache::get($cacheKey);

        if (! $data) {
            return response()->json(['message' => 'Exportación no encontrada o expirada.'], 404);
        }

        if ((int) ($data['user_id'] ?? 0) !== (int) $request->user()->id) {
            return response()->json(['message' => 'No autorizado.'], 403);
        }

        return response()->json([
            'status' => $data['status'] ?? 'processing',
            'message' => $data['message'] ?? null,
            'total' => $data['total'] ?? null,
            'filename' => $data['filename'] ?? null,
        ]);
    }

    public function exportDownload(Request $request, string $token): BinaryFileResponse|JsonResponse
    {
        $this->authorize('reports.export');

        $cacheKey = "export:nna:{$token}";
        $data = Cache::get($cacheKey);

        if (! $data || ($data['status'] ?? '') !== 'ready') {
            return response()->json(['message' => 'La exportación aún no está lista.'], 404);
        }

        if ((int) ($data['user_id'] ?? 0) !== (int) $request->user()->id) {
            return response()->json(['message' => 'No autorizado.'], 403);
        }

        $path = $data['path'] ?? null;
        if (! $path || ! Storage::disk('local')->exists($path)) {
            return response()->json(['message' => 'Archivo no encontrado.'], 404);
        }

        return response()->download(
            Storage::disk('local')->path($path),
            $data['filename'] ?? basename($path),
        )->deleteFileAfterSend(false);
    }

    private function dispatchAsyncExport(Request $request, string $format, ?int $operativoId, int $total): JsonResponse
    {
        $token = Str::uuid()->toString();
        $cacheKey = "export:nna:{$token}";

        Cache::put($cacheKey, [
            'status' => 'processing',
            'total' => $total,
            'format' => $format,
            'user_id' => $request->user()->id,
        ], now()->addHours(2));

        $connection = app()->environment('local', 'testing') ? 'sync' : config('queue.default');

        ExportNnaReportJob::dispatchAfterResponse($token, $format, $operativoId, (int) $request->user()->id)
            ->onConnection($connection);

        return response()->json([
            'async' => true,
            'token' => $token,
            'total' => $total,
            'message' => "Exportando {$total} registros. Esto puede tardar unos minutos.",
        ], 202);
    }

    private function baseQuery(?int $operativoId)
    {
        return NnaRegistration::query()
            ->when($operativoId, fn ($q) => $q->where('operativo_id', $operativoId))
            ->select([
                'id',
                'registration_code',
                'uuid',
                'first_name',
                'last_name',
                'age_years',
                'birth_date',
                'status',
                'registered_at',
                'notes',
            ])
            ->orderByDesc('registered_at');
    }

    private function streamCsv($query, string $filename): StreamedResponse
    {
        return response()->streamDownload(function () use ($query) {
            $handle = fopen('php://output', 'w');
            if ($handle === false) {
                return;
            }

            fputcsv($handle, [
                'Código', 'Nombres', 'Apellidos', 'Edad', 'Fecha nacimiento',
                'Estado registro', 'Fecha registro', 'Notas',
            ]);

            $query->cursor()->each(function ($nna) use ($handle) {
                fputcsv($handle, [
                    $nna->registration_code ?? $nna->uuid,
                    $nna->first_name,
                    $nna->last_name,
                    $nna->age_years,
                    $nna->birth_date?->format('Y-m-d'),
                    $nna->status?->value ?? $nna->status,
                    $nna->registered_at?->format('Y-m-d H:i'),
                    $nna->notes,
                ]);
            });

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    private function downloadPdf($query, string $filename, int $total): BinaryFileResponse
    {
        @ini_set('memory_limit', '512M');
        set_time_limit(600);

        $records = $query->get();

        $pdf = Pdf::loadView('reports.nna-pdf', [
            'records' => $records,
            'generatedAt' => now()->format('d/m/Y H:i'),
            'total' => $total,
        ])->setPaper('a4', 'landscape');

        return $pdf->download($filename);
    }
}
