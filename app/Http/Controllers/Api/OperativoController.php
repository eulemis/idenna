<?php

namespace App\Http\Controllers\Api;

use App\Enums\OperativoStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Operativo\StoreOperativoRequest;
use App\Http\Requests\Operativo\UpdateOperativoRequest;
use App\Http\Resources\OperativoResource;
use App\Models\Operativo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class OperativoController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('operativos.view');

        $query = Operativo::query()
            ->with('creator')
            ->latest('started_at');

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        if ($request->filled('type')) {
            $query->where('type', $request->string('type'));
        }

        if ($request->boolean('active_only')) {
            $query->where('status', OperativoStatus::Active);
        }

        return OperativoResource::collection(
            $query->paginate($request->integer('per_page', 15))
        );
    }

    public function store(StoreOperativoRequest $request): JsonResponse
    {
        $operativo = Operativo::query()->create([
            ...$request->validated(),
            'status' => $request->validated('status') ?? OperativoStatus::Draft,
            'created_by' => $request->user()?->id,
        ]);

        $operativo->load('creator');

        return response()->json([
            'message' => 'Operativo creado correctamente.',
            'data' => OperativoResource::make($operativo),
        ], 201);
    }

    public function show(Operativo $operativo): JsonResponse
    {
        $this->authorize('operativos.view');

        $operativo->load('creator');

        return response()->json([
            'data' => OperativoResource::make($operativo),
        ]);
    }

    public function update(UpdateOperativoRequest $request, Operativo $operativo): JsonResponse
    {
        $operativo->update($request->validated());
        $operativo->load('creator');

        return response()->json([
            'message' => 'Operativo actualizado correctamente.',
            'data' => OperativoResource::make($operativo),
        ]);
    }

    public function destroy(Operativo $operativo): JsonResponse
    {
        $this->authorize('operativos.manage');

        $operativo->delete();

        return response()->json([
            'message' => 'Operativo eliminado correctamente.',
        ]);
    }
}
