<?php

namespace App\Services;

use App\Models\Estado;
use App\Models\NnaRegistration;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class DashboardService
{
    public function getStats(?int $operativoId = null): array
    {
        $baseQuery = NnaRegistration::query()->when($operativoId, fn ($q) => $q->where('operativo_id', $operativoId));

        $total = (clone $baseQuery)->count();
        $today = (clone $baseQuery)->whereDate('registered_at', today())->count();
        $draft = (clone $baseQuery)->where('status', 'draft')->count();
        $synced = (clone $baseQuery)->where('status', 'synced')->count();

        $byEstado = (clone $baseQuery)
            ->select('estado_id', DB::raw('count(*) as total'))
            ->whereNotNull('estado_id')
            ->groupBy('estado_id')
            ->get()
            ->map(function ($row) {
                $estado = Estado::find($row->estado_id);

                return [
                    'estado_id' => $row->estado_id,
                    'name' => $estado?->name ?? 'Sin estado',
                    'total' => $row->total,
                ];
            });

        $byGender = (clone $baseQuery)
            ->select('gender_id', DB::raw('count(*) as total'))
            ->whereNotNull('gender_id')
            ->groupBy('gender_id')
            ->get()
            ->map(function ($row) {
                return [
                    'gender_id' => $row->gender_id,
                    'name' => DB::table('catalogs')->where('id', $row->gender_id)->value('name') ?? 'N/D',
                    'total' => $row->total,
                ];
            });

        $byAge = (clone $baseQuery)
            ->select(
                DB::raw("CASE
                    WHEN age_years IS NULL THEN 'Sin edad'
                    WHEN age_years <= 5 THEN '0-5'
                    WHEN age_years <= 11 THEN '6-11'
                    WHEN age_years <= 17 THEN '12-17'
                    ELSE '18+'
                END as age_group"),
                DB::raw('count(*) as total')
            )
            ->groupBy('age_group')
            ->get()
            ->map(fn ($r) => ['group' => $r->age_group, 'total' => $r->total]);

        $timeline = (clone $baseQuery)
            ->select(DB::raw('DATE(registered_at) as date'), DB::raw('count(*) as total'))
            ->whereNotNull('registered_at')
            ->where('registered_at', '>=', now()->subDays(30))
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->map(fn ($r) => ['date' => $r->date, 'total' => $r->total]);

        $byUser = (clone $baseQuery)
            ->select('registered_by', DB::raw('count(*) as total'))
            ->whereNotNull('registered_by')
            ->groupBy('registered_by')
            ->orderByDesc('total')
            ->limit(10)
            ->get()
            ->map(function ($row) {
                $user = User::find($row->registered_by);

                return [
                    'user_id' => $row->registered_by,
                    'name' => $user?->name ?? 'Desconocido',
                    'total' => $row->total,
                ];
            });

        return [
            'kpis' => [
                'total' => $total,
                'today' => $today,
                'draft' => $draft,
                'synced' => $synced,
            ],
            'by_estado' => $byEstado,
            'by_gender' => $byGender,
            'by_age_group' => $byAge,
            'timeline' => $timeline,
            'productivity_by_user' => $byUser,
        ];
    }
}
