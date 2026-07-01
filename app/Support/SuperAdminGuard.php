<?php

namespace App\Support;

use App\Models\User;

class SuperAdminGuard
{
    public const ROLE = 'super-admin';

    public static function exists(?int $exceptUserId = null): bool
    {
        $query = User::role(self::ROLE);

        if ($exceptUserId !== null) {
            $query->where('id', '!=', $exceptUserId);
        }

        return $query->exists();
    }

    public static function assertAssignable(?int $targetUserId = null): void
    {
        if (self::exists($targetUserId)) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'role' => ['Solo puede existir un usuario super administrador en el sistema.'],
            ]);
        }
    }
}
