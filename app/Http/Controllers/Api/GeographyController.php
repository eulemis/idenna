<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\EstadoResource;
use App\Http\Resources\MunicipioResource;
use App\Http\Resources\ParroquiaResource;
use App\Models\Estado;
use App\Models\Municipio;
use App\Models\Parroquia;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class GeographyController extends Controller
{
    public function estados(): AnonymousResourceCollection
    {
        $this->authorize('catalogs.view');

        return EstadoResource::collection(
            Estado::query()->venezuela()->where('is_active', true)->orderBy('name')->get()
        );
    }

    public function municipios(Estado $estado): AnonymousResourceCollection
    {
        $this->authorize('catalogs.view');

        return MunicipioResource::collection(
            $estado->municipios()->where('is_active', true)->orderBy('name')->get()
        );
    }

    public function parroquias(Municipio $municipio): AnonymousResourceCollection
    {
        $this->authorize('catalogs.view');

        return ParroquiaResource::collection(
            $municipio->parroquias()->where('is_active', true)->orderBy('name')->get()
        );
    }

    public function bundle(Request $request): JsonResponse
    {
        $this->authorize('catalogs.view');

        $estados = Estado::query()
            ->venezuela()
            ->where('is_active', true)
            ->with(['municipios' => fn ($q) => $q->where('is_active', true)->orderBy('name')->with(['parroquias' => fn ($q2) => $q2->where('is_active', true)->orderBy('name')])])
            ->orderBy('name')
            ->get();

        return response()->json(['data' => EstadoResource::collection($estados)]);
    }
}
