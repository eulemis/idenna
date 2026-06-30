<?php

namespace App\Http\Controllers\Api;

use App\Enums\AttentionLocationType;
use App\Http\Controllers\Controller;
use App\Http\Resources\AttentionLocationResource;
use App\Models\AttentionLocation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

class AttentionLocationController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('catalogs.view');

        $query = AttentionLocation::query()->orderBy('name');

        if ($request->filled('type')) {
            $query->where('type', $request->string('type'));
        }

        if ($request->filled('operativo_id')) {
            $query->where('operativo_id', $request->integer('operativo_id'));
        }

        if ($request->boolean('active_only', true)) {
            $query->where('is_active', true);
        }

        return AttentionLocationResource::collection(
            $query->paginate($request->integer('per_page', 50))
        );
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('catalogs.manage');

        $validated = $request->validate([
            'operativo_id' => ['nullable', 'exists:operativos,id'],
            'type' => ['required', Rule::enum(AttentionLocationType::class)],
            'name' => ['required', 'string', 'max:255'],
            'estado_id' => ['nullable', 'exists:estados,id'],
            'municipio_id' => ['nullable', 'exists:municipios,id'],
            'parroquia_id' => ['nullable', 'exists:parroquias,id'],
            'address' => ['nullable', 'string'],
            'latitude' => ['nullable', 'numeric'],
            'longitude' => ['nullable', 'numeric'],
            'is_active' => ['nullable', 'boolean'],
            'metadata' => ['nullable', 'array'],
        ]);

        $location = AttentionLocation::query()->create($validated);

        return response()->json([
            'message' => 'Ubicación creada correctamente.',
            'data' => AttentionLocationResource::make($location),
        ], 201);
    }

    public function update(Request $request, AttentionLocation $attentionLocation): JsonResponse
    {
        $this->authorize('catalogs.manage');

        $validated = $request->validate([
            'operativo_id' => ['nullable', 'exists:operativos,id'],
            'type' => ['sometimes', Rule::enum(AttentionLocationType::class)],
            'name' => ['sometimes', 'string', 'max:255'],
            'estado_id' => ['nullable', 'exists:estados,id'],
            'municipio_id' => ['nullable', 'exists:municipios,id'],
            'parroquia_id' => ['nullable', 'exists:parroquias,id'],
            'address' => ['nullable', 'string'],
            'latitude' => ['nullable', 'numeric'],
            'longitude' => ['nullable', 'numeric'],
            'is_active' => ['nullable', 'boolean'],
            'metadata' => ['nullable', 'array'],
        ]);

        $attentionLocation->update($validated);

        return response()->json([
            'message' => 'Ubicación actualizada correctamente.',
            'data' => AttentionLocationResource::make($attentionLocation),
        ]);
    }

    public function destroy(AttentionLocation $attentionLocation): JsonResponse
    {
        $this->authorize('catalogs.manage');

        $attentionLocation->delete();

        return response()->json(['message' => 'Ubicación eliminada correctamente.']);
    }
}
