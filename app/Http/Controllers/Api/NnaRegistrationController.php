<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\NnaRegistrationResource;
use App\Models\NnaRegistration;
use App\Models\NnaPhoto;
use App\Services\NnaRegistrationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class NnaRegistrationController extends Controller
{
    public function __construct(private readonly NnaRegistrationService $service) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('nna.view');

        $query = NnaRegistration::query()
            ->select([
                'id', 'uuid', 'local_uuid', 'operativo_id', 'registration_code',
                'first_name', 'last_name', 'birth_date', 'age_years', 'status',
                'registered_at', 'synced_at', 'created_at',
            ])
            ->latest('registered_at');

        if ($request->filled('operativo_id')) {
            $query->where('operativo_id', $request->integer('operativo_id'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        if ($request->filled('search')) {
            $search = $request->string('search');
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('registration_code', 'like', "%{$search}%");
            });
        }

        return NnaRegistrationResource::collection(
            $query->paginate($request->integer('per_page', 20))
        );
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('nna.register');

        $validated = $this->validatePayload($request);
        $nna = $this->service->create($validated, (int) $request->user()->id);

        return response()->json([
            'message' => 'Registro NNA creado correctamente.',
            'data' => NnaRegistrationResource::make($nna),
        ], 201);
    }

    public function show(NnaRegistration $nnaRegistration): JsonResponse
    {
        $this->authorize('nna.view');

        $nnaRegistration->load(['acompanantes', 'discapacidades', 'necesidades', 'photos']);

        return response()->json([
            'data' => NnaRegistrationResource::make($nnaRegistration),
        ]);
    }

    public function update(Request $request, NnaRegistration $nnaRegistration): JsonResponse
    {
        $this->authorize('nna.edit');

        $validated = $this->validatePayload($request, updating: true);
        $nna = $this->service->update($nnaRegistration, $validated);

        return response()->json([
            'message' => 'Registro NNA actualizado correctamente.',
            'data' => NnaRegistrationResource::make($nna),
        ]);
    }

    public function destroy(NnaRegistration $nnaRegistration): JsonResponse
    {
        $this->authorize('nna.edit');

        $nnaRegistration->delete();

        return response()->json(['message' => 'Registro NNA eliminado correctamente.']);
    }

    public function syncBatch(Request $request): JsonResponse
    {
        $this->authorize('nna.register');

        $payload = $request->validate([
            'records' => ['required', 'array', 'min:1'],
            'records.*' => ['array'],
        ]);

        $results = [];
        foreach ($payload['records'] as $record) {
            $nna = $this->service->upsertFromSync($record, (int) $request->user()->id);
            $results[] = NnaRegistrationResource::make(
                $nna->load(['acompanantes', 'discapacidades', 'necesidades', 'photos'])
            );
        }

        return response()->json([
            'message' => 'Sincronización completada.',
            'data' => $results,
        ]);
    }

    public function uploadPhoto(Request $request, NnaRegistration $nnaRegistration): JsonResponse
    {
        $this->authorize('nna.register');

        $validated = $request->validate([
            'photo' => ['required', 'image', 'max:5120'],
            'is_primary' => ['nullable', 'boolean'],
        ]);

        $file = $validated['photo'];
        $filename = Str::uuid().'.'.$file->getClientOriginalExtension();
        $path = $file->storeAs("nna/{$nnaRegistration->uuid}", $filename, 'public');

        if ($request->boolean('is_primary')) {
            $nnaRegistration->photos()->update(['is_primary' => false]);
        }

        $photo = NnaPhoto::query()->create([
            'nna_registration_id' => $nnaRegistration->id,
            'disk' => 'public',
            'path' => $path,
            'mime_type' => $file->getMimeType(),
            'size_bytes' => $file->getSize(),
            'is_primary' => $request->boolean('is_primary', $nnaRegistration->photos()->count() === 0),
        ]);

        return response()->json([
            'message' => 'Fotografía cargada correctamente.',
            'data' => [
                'id' => $photo->id,
                'url' => Storage::disk('public')->url($path),
                'is_primary' => $photo->is_primary,
            ],
        ], 201);
    }

    private function validatePayload(Request $request, bool $updating = false): array
    {
        $rules = [
            'local_uuid' => [$updating ? 'sometimes' : 'nullable', 'uuid'],
            'operativo_id' => [$updating ? 'sometimes' : 'required', 'exists:operativos,id'],
            'registration_code' => ['nullable', 'string', 'max:30'],
            'first_name' => [$updating ? 'sometimes' : 'required', 'string', 'max:120'],
            'last_name' => [$updating ? 'sometimes' : 'required', 'string', 'max:120'],
            'birth_date' => ['nullable', 'date'],
            'age_years' => ['nullable', 'integer', 'min:0', 'max:25'],
            'gender_id' => ['nullable', 'exists:catalogs,id'],
            'skin_color_id' => ['nullable', 'exists:catalogs,id'],
            'eye_color_id' => ['nullable', 'exists:catalogs,id'],
            'hair_color_id' => ['nullable', 'exists:catalogs,id'],
            'size_id' => ['nullable', 'exists:catalogs,id'],
            'estado_id' => ['nullable', 'exists:estados,id'],
            'municipio_id' => ['nullable', 'exists:municipios,id'],
            'parroquia_id' => ['nullable', 'exists:parroquias,id'],
            'attention_location_id' => ['nullable', 'exists:attention_locations,id'],
            'lugar_nna_id' => ['nullable', 'exists:catalogs,id'],
            'notes' => ['nullable', 'string'],
            'status' => ['nullable', 'string'],
            'device_name' => ['nullable', 'string', 'max:255'],
            'latitude' => ['nullable', 'numeric'],
            'longitude' => ['nullable', 'numeric'],
            'metadata' => ['nullable', 'array'],
            'discapacidad_ids' => ['nullable', 'array'],
            'discapacidad_ids.*' => ['integer', 'exists:catalogs,id'],
            'necesidad_ids' => ['nullable', 'array'],
            'necesidad_ids.*' => ['integer', 'exists:catalogs,id'],
            'acompanantes' => ['nullable', 'array'],
            'acompanantes.*.first_name' => ['required_with:acompanantes', 'string', 'max:120'],
            'acompanantes.*.last_name' => ['nullable', 'string', 'max:120'],
            'acompanantes.*.document_id' => ['nullable', 'string', 'max:30'],
            'acompanantes.*.relationship_id' => ['nullable', 'exists:catalogs,id'],
            'acompanantes.*.phone' => ['nullable', 'string', 'max:20'],
             'acompanantes.*.is_primary_contact' => ['nullable', 'boolean'],
        ];

        return $request->validate($rules);
    }
}
