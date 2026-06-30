<?php

namespace App\Http\Controllers\Api;

use App\Enums\CatalogType;
use App\Http\Controllers\Controller;
use App\Http\Resources\CatalogResource;
use App\Models\Catalog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

class CatalogController extends Controller
{
    public function types(): JsonResponse
    {
        $this->authorize('catalogs.view');

        return response()->json([
            'data' => collect(CatalogType::cases())->map(fn (CatalogType $type) => [
                'value' => $type->value,
                'label' => $type->label(),
            ]),
        ]);
    }

    public function index(string $type): AnonymousResourceCollection
    {
        $this->authorize('catalogs.view');
        $this->assertValidType($type);

        $items = Catalog::query()
            ->where('type', $type)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->paginate(50);

        return CatalogResource::collection($items);
    }

    public function store(Request $request, string $type): JsonResponse
    {
        $this->authorize('catalogs.manage');
        $this->assertValidType($type);

        $validated = $request->validate([
            'code' => ['required', 'string', 'max:50', Rule::unique('catalogs', 'code')->where('type', $type)],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
            'metadata' => ['nullable', 'array'],
        ]);

        $catalog = Catalog::query()->create([
            ...$validated,
            'type' => $type,
        ]);

        return response()->json([
            'message' => 'Catálogo creado correctamente.',
            'data' => CatalogResource::make($catalog),
        ], 201);
    }

    public function update(Request $request, string $type, Catalog $catalog): JsonResponse
    {
        $this->authorize('catalogs.manage');
        $this->assertValidType($type);

        $validated = $request->validate([
            'code' => ['sometimes', 'string', 'max:50', Rule::unique('catalogs', 'code')->where('type', $type)->ignore($catalog->id)],
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
            'metadata' => ['nullable', 'array'],
        ]);

        $catalog->update($validated);

        return response()->json([
            'message' => 'Catálogo actualizado correctamente.',
            'data' => CatalogResource::make($catalog),
        ]);
    }

    public function destroy(string $type, Catalog $catalog): JsonResponse
    {
        $this->authorize('catalogs.manage');
        $this->assertValidType($type);

        $catalog->delete();

        return response()->json(['message' => 'Catálogo eliminado correctamente.']);
    }

    public function bundle(): JsonResponse
    {
        $this->authorize('catalogs.view');

        $grouped = Catalog::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get()
            ->groupBy(fn (Catalog $item) => $item->type instanceof CatalogType ? $item->type->value : $item->type)
            ->map(fn ($items) => CatalogResource::collection($items));

        return response()->json(['data' => $grouped]);
    }

    private function assertValidType(string $type): void
    {
        abort_unless(in_array($type, array_column(CatalogType::cases(), 'value'), true), 404, 'Tipo de catálogo no válido.');
    }
}
