<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ImportBatch;
use App\Services\NnaImportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ImportController extends Controller
{
    public function __construct(private readonly NnaImportService $importService) {}

    public function preview(Request $request): JsonResponse
    {
        $this->authorize('imports.manage');

        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt,xlsx,xls', 'max:10240'],
        ]);

        return response()->json([
            'data' => $this->importService->parseFile($request->file('file')),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('imports.manage');

        $validated = $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt,xlsx,xls', 'max:10240'],
            'operativo_id' => ['required', 'exists:operativos,id'],
            'column_mapping' => ['required'],
        ]);

        $columnMapping = is_string($validated['column_mapping'])
            ? json_decode($validated['column_mapping'], true, 512, JSON_THROW_ON_ERROR)
            : $validated['column_mapping'];

        if (empty($columnMapping['first_name']) || empty($columnMapping['last_name'])) {
            return response()->json(['message' => 'Mapeo de nombres y apellidos es obligatorio.'], 422);
        }

        $batch = $this->importService->processImport(
            $request->file('file'),
            (int) $validated['operativo_id'],
            (int) $request->user()->id,
            $columnMapping,
        );

        return response()->json([
            'message' => 'Importación procesada.',
            'data' => $batch,
        ], 201);
    }

    public function index(): JsonResponse
    {
        $this->authorize('imports.manage');

        return response()->json(
            ImportBatch::query()
                ->with(['user:id,name', 'operativo:id,name,code'])
                ->latest()
                ->paginate(20)
        );
    }
}
