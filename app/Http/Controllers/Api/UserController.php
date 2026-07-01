<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\User\StoreUserRequest;
use App\Http\Requests\User\UpdateUserRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Support\SuperAdminGuard;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('users.manage');

        $query = User::query()
            ->with(['currentOperativo', 'roles'])
            ->latest('id');

        if ($request->filled('search')) {
            $search = $request->string('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('document_id', 'like', "%{$search}%");
            });
        }

        if ($request->filled('role')) {
            $query->role($request->string('role'));
        }

        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        return UserResource::collection(
            $query->paginate($request->integer('per_page', $request->integer('limit', 15)))
        );
    }

    public function store(StoreUserRequest $request): JsonResponse
    {
        $data = $request->validated();
        $role = $data['role'];
        if ($role === SuperAdminGuard::ROLE) {
            SuperAdminGuard::assertAssignable();
        }
        unset($data['role'], $data['password_confirmation']);

        $user = User::query()->create([
            ...collect($data)->except('password')->all(),
            'password' => Hash::make($data['password']),
            'is_active' => $data['is_active'] ?? true,
        ]);

        $user->syncRoles([$role]);
        $user->load(['currentOperativo', 'roles']);

        return response()->json([
            'message' => 'Usuario creado correctamente.',
            'data' => UserResource::make($user),
        ], 201);
    }

    public function show(User $user): JsonResponse
    {
        $this->authorize('users.manage');

        $user->load(['currentOperativo', 'roles']);

        return response()->json([
            'data' => UserResource::make($user),
        ]);
    }

    public function update(UpdateUserRequest $request, User $user): JsonResponse
    {
        $data = $request->validated();
        $role = $data['role'] ?? null;

        if ($role === SuperAdminGuard::ROLE && ! $user->hasRole(SuperAdminGuard::ROLE)) {
            SuperAdminGuard::assertAssignable();
        }

        if ($user->hasRole(SuperAdminGuard::ROLE) && $role && $role !== SuperAdminGuard::ROLE) {
            return response()->json([
                'message' => 'No se puede cambiar el rol del único super administrador.',
            ], 422);
        }

        unset($data['role'], $data['password_confirmation']);

        if (! empty($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        } else {
            unset($data['password']);
        }

        $user->update($data);

        if ($role) {
            $user->syncRoles([$role]);
        }

        $user->load(['currentOperativo', 'roles']);

        return response()->json([
            'message' => 'Usuario actualizado correctamente.',
            'data' => UserResource::make($user),
        ]);
    }

    public function destroy(Request $request, User $user): JsonResponse
    {
        $this->authorize('users.manage');

        if ((int) $request->user()->id === (int) $user->id) {
            return response()->json([
                'message' => 'No puede eliminar su propio usuario.',
            ], 422);
        }

        if ($user->hasRole(SuperAdminGuard::ROLE)) {
            return response()->json([
                'message' => 'No se puede eliminar al super administrador del sistema.',
            ], 422);
        }

        $user->delete();

        return response()->json([
            'message' => 'Usuario eliminado correctamente.',
        ]);
    }

    public function roles(): JsonResponse
    {
        $this->authorize('users.manage');

        $roles = Role::query()
            ->orderBy('name')
            ->pluck('name')
            ->reject(fn (string $name) => $name === SuperAdminGuard::ROLE)
            ->values();

        return response()->json([
            'data' => $roles,
        ]);
    }
}
