<?php

namespace App\Services;

use App\Models\Estado;
use App\Models\NnaRegistration;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class DashboardService
{
    /**
     * @param  array{operativo_id?: int|null, estado_id?: int|null, from?: string|null, to?: string|null}  $filters
     */
    public function getStats(array $filters = []): array
    {
        $baseQuery = $this->filteredQuery($filters);

        $total = (clone $baseQuery)->count();
        $today = (clone $baseQuery)->whereDate('registered_at', today())->count();
        $draft = (clone $baseQuery)->where('status', 'draft')->count();
        $synced = (clone $baseQuery)->where('status', 'synced')->count();

        $byEstado = (clone $baseQuery)
            ->select('estado_id', DB::raw('count(*) as total'))
            ->whereNotNull('estado_id')
            ->groupBy('estado_id')
            ->orderByDesc('total')
            ->get()
            ->map(function ($row) {
                $estado = Estado::find($row->estado_id);

                return [
                    'estado_id' => $row->estado_id,
                    'name' => $estado?->name ?? 'Sin estado',
                    'total' => (int) $row->total,
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
                    'total' => (int) $row->total,
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
            ->map(fn ($r) => ['group' => $r->age_group, 'total' => (int) $r->total]);

        $timeline = (clone $baseQuery)
            ->select(DB::raw('DATE(registered_at) as date'), DB::raw('count(*) as total'))
            ->whereNotNull('registered_at')
            ->where('registered_at', '>=', now()->subDays(30))
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->map(fn ($r) => ['date' => $r->date, 'total' => (int) $r->total]);

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
                    'total' => (int) $row->total,
                ];
            });

        $byLugar = (clone $baseQuery)
            ->select('lugar_nna_id', DB::raw('count(*) as total'))
            ->whereNotNull('lugar_nna_id')
            ->groupBy('lugar_nna_id')
            ->orderByDesc('total')
            ->get()
            ->map(function ($row) {
                return [
                    'lugar_nna_id' => $row->lugar_nna_id,
                    'name' => DB::table('catalogs')->where('id', $row->lugar_nna_id)->value('name') ?? 'N/D',
                    'total' => (int) $row->total,
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
            'by_lugar' => $byLugar,
            'filters_applied' => array_filter($filters),
        ];
    }

    /**
     * @param  array{operativo_id?: int|null, estado_id?: int|null, from?: string|null, to?: string|null}  $filters
     */
    private function filteredQuery(array $filters)
    {
        $query = NnaRegistration::query();

        if (! empty($filters['operativo_id'])) {
            $query->where('operativo_id', (int) $filters['operativo_id']);
        }

        if (! empty($filters['estado_id'])) {
            $query->where('estado_id', (int) $filters['estado_id']);
        }

        if (! empty($filters['from'])) {
            $query->whereDate('registered_at', '>=', $filters['from']);
        }

        if (! empty($filters['to'])) {
            $query->whereDate('registered_at', '<=', $filters['to']);
        }

        return $query;
    }
}
