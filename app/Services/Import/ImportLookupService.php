<?php

namespace App\Services\Import;

use App\Models\Catalog;
use App\Models\Estado;
use App\Models\Municipio;
use App\Models\Parroquia;
use Illuminate\Support\Str;

class ImportLookupService
{
    /** @var array<string, int> */
    private array $catalogCache = [];

    /** @var array<string, int> */
    private array $estadoCache = [];

    /** @var array<string, int> */
    private array $municipioCache = [];

    /** @var array<string, int> */
    private array $parroquiaCache = [];

    /** @var array<string, list<string>> */
    private const ESTADO_ALIASES = [
        'la guaira' => ['la guaira', 'vargas', 'estado vargas'],
        'distrito capital' => ['distrito capital', 'dc', 'capital'],
        'miranda' => ['miranda'],
        'zulia' => ['zulia'],
        'carabobo' => ['carabobo'],
    ];

    /** @var array<string, string> */
    private const NECESIDAD_ALIASES = [
        'alimentos' => 'ALIMENTACION',
        'alimentacion' => 'ALIMENTACION',
        'refugio' => 'REFUGIO',
        'medicina' => 'SALUD',
        'salud' => 'SALUD',
        'apoyo psicologico' => 'PSICOLOGICA',
        'apoyo psicológico' => 'PSICOLOGICA',
        'reencuentro' => 'REENCUENTRO',
        'documentos' => 'DOCUMENTOS',
        'documentacion' => 'DOCUMENTOS',
    ];

    /** @var array<string, string> */
    private const DISCAPACIDAD_ALIASES = [
        'fisica' => 'FISICA',
        'visual' => 'VISUAL',
        'auditiva' => 'AUDITIVA',
        'intelectual' => 'INTELECTUAL',
        'psicosocial' => 'PSICOSOCIAL',
        'autista' => 'PSICOSOCIAL',
        'autismo' => 'PSICOSOCIAL',
        'multiple' => 'MULTIPLE',
    ];

    /** @var array<string, string> */
    private const LUGAR_NNA_ALIASES = [
        'hospital' => 'HOSPITAL',
        'refugio' => 'REFUGIO',
        'campamento' => 'CAMPAMENTO',
        'parque' => 'PARQUE',
        'plaza' => 'PLAZA',
    ];

    public function warmUp(): void
    {
        foreach (Catalog::query()->where('is_active', true)->get(['id', 'type', 'code', 'name']) as $catalog) {
            $type = $catalog->type instanceof \App\Enums\CatalogType ? $catalog->type->value : (string) $catalog->type;
            $key = $this->catalogKey($type, $catalog->name);
            $this->catalogCache[$key] = $catalog->id;
            $this->catalogCache[$this->catalogKey($type, $catalog->code)] = $catalog->id;
        }

        foreach (Estado::query()->where('is_active', true)->get(['id', 'name']) as $estado) {
            $this->estadoCache[$this->normalize($estado->name)] = $estado->id;
        }

        foreach (Municipio::query()->where('is_active', true)->get(['id', 'estado_id', 'name']) as $municipio) {
            $this->municipioCache[$this->geoKey($municipio->estado_id, $municipio->name)] = $municipio->id;
        }

        foreach (Parroquia::query()->where('is_active', true)->get(['id', 'municipio_id', 'name']) as $parroquia) {
            $this->parroquiaCache[$this->geoKey($parroquia->municipio_id, $parroquia->name)] = $parroquia->id;
        }
    }

    public function findCatalog(string $type, ?string $value): ?int
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        $normalized = $this->normalize($value);
        if (isset($this->catalogCache[$this->catalogKey($type, $value)])) {
            return $this->catalogCache[$this->catalogKey($type, $value)];
        }

        $direct = Catalog::query()
            ->where('type', $type)
            ->where('is_active', true)
            ->where(function ($q) use ($value, $normalized) {
                $q->whereRaw('LOWER(name) = ?', [$normalized])
                    ->orWhereRaw('LOWER(code) = ?', [$normalized])
                    ->orWhere('name', 'like', '%'.$value.'%');
            })
            ->value('id');

        if ($direct) {
            $this->catalogCache[$this->catalogKey($type, $value)] = (int) $direct;

            return (int) $direct;
        }

        return $this->fuzzyCatalogMatch($type, $normalized);
    }

    public function findGenero(?string $value): ?int
    {
        if (! $value) {
            return null;
        }

        $normalized = $this->normalize($value);
        if (str_contains($normalized, 'masc')) {
            return $this->findCatalog('genero', 'Masculino');
        }
        if (str_contains($normalized, 'fem')) {
            return $this->findCatalog('genero', 'Femenino');
        }

        return $this->findCatalog('genero', $value);
    }

    public function findColorPiel(?string $value): ?int
    {
        if (! $value) {
            return null;
        }

        $normalized = $this->normalize($value);
        if (str_contains($normalized, 'blanc') || str_contains($normalized, 'clar')) {
            return $this->findCatalog('color_piel', 'Clara');
        }
        if (str_contains($normalized, 'moren') || str_contains($normalized, 'negr')) {
            return $this->findCatalog('color_piel', 'Morena');
        }
        if (str_contains($normalized, 'medi')) {
            return $this->findCatalog('color_piel', 'Media');
        }

        return $this->findCatalog('color_piel', $value);
    }

    public function findColorCabello(?string $value): ?int
    {
        if (! $value) {
            return null;
        }

        $normalized = $this->normalize($value);
        if (str_contains($normalized, 'castan')) {
            return $this->findCatalog('color_cabello', 'Castaño');
        }
        if (str_contains($normalized, 'negro')) {
            return $this->findCatalog('color_cabello', 'Negro');
        }
        if (str_contains($normalized, 'rubio')) {
            return $this->findCatalog('color_cabello', 'Rubio');
        }

        return $this->findCatalog('color_cabello', $value);
    }

    public function findColorOjos(?string $value): ?int
    {
        if (! $value) {
            return null;
        }

        $normalized = $this->normalize($value);
        if (str_contains($normalized, 'marron') || str_contains($normalized, 'cafe')) {
            return $this->findCatalog('color_ojos', 'Café');
        }
        if (str_contains($normalized, 'negro')) {
            return $this->findCatalog('color_ojos', 'Negro');
        }
        if (str_contains($normalized, 'verde')) {
            return $this->findCatalog('color_ojos', 'Verde');
        }
        if (str_contains($normalized, 'azul')) {
            return $this->findCatalog('color_ojos', 'Azul');
        }

        return $this->findCatalog('color_ojos', $value);
    }

    public function findParentesco(?string $value): ?int
    {
        if (! $value) {
            return null;
        }

        $normalized = $this->normalize($value);
        if (str_contains($normalized, 'madre')) {
            return $this->findCatalog('parentesco', 'Madre');
        }
        if (str_contains($normalized, 'padre')) {
            return $this->findCatalog('parentesco', 'Padre');
        }
        if (str_contains($normalized, 'tio') || str_contains($normalized, 'tia')) {
            return $this->findCatalog('parentesco', 'Familiar');
        }
        if (str_contains($normalized, 'abuel')) {
            return $this->findCatalog('parentesco', 'Familiar');
        }

        return $this->findCatalog('parentesco', $value);
    }

    public function findOrganoActuante(?string $value): ?int
    {
        if (! $value) {
            return null;
        }

        $normalized = $this->normalize($value);
        if (str_contains($normalized, 'cpnna')) {
            return $this->findCatalog('organo_actuante', 'CPNNA');
        }
        if (str_contains($normalized, 'cmdnna')) {
            return $this->findCatalog('organo_actuante', 'CMDNNA');
        }
        if (str_contains($normalized, 'tribunal')) {
            return $this->findCatalog('organo_actuante', 'Tribunales de Protección');
        }

        return $this->findCatalog('organo_actuante', $value);
    }

    public function findTipoMedida(?string $value): ?int
    {
        if (! $value) {
            return null;
        }

        $normalized = $this->normalize($value);
        if (str_contains($normalized, 'abrigo')) {
            return $this->findCatalog('tipo_medida', 'Abrigo');
        }
        if (str_contains($normalized, 'internacion') || str_contains($normalized, 'hospital')) {
            return $this->findCatalog('tipo_medida', 'Internación en centro hospitalario');
        }
        if (str_contains($normalized, 'crianza') || str_contains($normalized, 'responsabilidad')) {
            return $this->findCatalog('tipo_medida', 'Responsabilidad de crianza');
        }

        return $this->findCatalog('tipo_medida', $value);
    }

    public function findLugarNna(?string $locationType, ?string $refugeType = null): ?int
    {
        if ($locationType && str_contains($this->normalize($locationType), 'hospital')) {
            return $this->findCatalog('lugar_nna', 'Hospital');
        }

        if ($refugeType) {
            $code = self::LUGAR_NNA_ALIASES[$this->normalize($refugeType)] ?? null;
            if ($code) {
                return $this->findCatalog('lugar_nna', $code);
            }
        }

        if ($locationType && str_contains($this->normalize($locationType), 'refugio')) {
            return $this->findCatalog('lugar_nna', 'Refugio');
        }

        return null;
    }

    /**
     * @return list<int>
     */
    public function mapNecesidades(?string $value): array
    {
        if (! $value) {
            return [];
        }

        $ids = [];
        foreach (preg_split('/[,;]+/', $value) as $part) {
            $part = trim($part);
            if ($part === '') {
                continue;
            }
            $normalized = $this->normalize($part);
            $code = self::NECESIDAD_ALIASES[$normalized] ?? null;
            if (! $code) {
                foreach (self::NECESIDAD_ALIASES as $needle => $catalogCode) {
                    if (str_contains($normalized, $needle)) {
                        $code = $catalogCode;
                        break;
                    }
                }
            }
            if ($code) {
                $id = $this->findCatalog('necesidad', $code);
                if ($id) {
                    $ids[] = $id;
                }
            }
        }

        return array_values(array_unique($ids));
    }

    /**
     * @return list<int>
     */
    public function mapDiscapacidades(?string $value): array
    {
        if (! $value) {
            return [];
        }

        $normalized = $this->normalize($value);
        if (in_array($normalized, ['ninguna', 'no registra', 'no', 'na', 'n/a', 'sin discapacidad'], true)) {
            return [];
        }

        foreach (self::DISCAPACIDAD_ALIASES as $needle => $code) {
            if (str_contains($normalized, $needle)) {
                $id = $this->findCatalog('tipo_discapacidad', $code);

                return $id ? [$id] : [];
            }
        }

        return [];
    }

    public function findEstado(?string $value): ?int
    {
        if (! $value) {
            return null;
        }

        $normalized = $this->normalize($value);
        if (isset($this->estadoCache[$normalized])) {
            return $this->estadoCache[$normalized];
        }

        foreach (self::ESTADO_ALIASES as $canonical => $aliases) {
            if (in_array($normalized, $aliases, true) || str_contains($normalized, $canonical)) {
                foreach ($aliases as $alias) {
                    if (isset($this->estadoCache[$alias])) {
                        return $this->estadoCache[$alias];
                    }
                }
            }
        }

        foreach ($this->estadoCache as $name => $id) {
            if (str_contains($name, $normalized) || str_contains($normalized, $name)) {
                return $id;
            }
        }

        return null;
    }

    public function findMunicipio(?int $estadoId, ?string $value): ?int
    {
        if (! $estadoId || ! $value) {
            return null;
        }

        $key = $this->geoKey($estadoId, $value);
        if (isset($this->municipioCache[$key])) {
            return $this->municipioCache[$key];
        }

        $normalized = $this->normalize($value);
        foreach ($this->municipioCache as $cacheKey => $id) {
            if (! str_starts_with($cacheKey, $estadoId.':')) {
                continue;
            }
            $name = substr($cacheKey, strlen((string) $estadoId) + 1);
            if (str_contains($name, $normalized) || str_contains($normalized, $name)) {
                return $id;
            }
        }

        return Municipio::query()
            ->where('estado_id', $estadoId)
            ->where('is_active', true)
            ->where('name', 'like', '%'.trim($value).'%')
            ->value('id');
    }

    public function findParroquia(?int $municipioId, ?string $value): ?int
    {
        if (! $municipioId || ! $value) {
            return null;
        }

        $key = $this->geoKey($municipioId, $value);
        if (isset($this->parroquiaCache[$key])) {
            return $this->parroquiaCache[$key];
        }

        $normalized = $this->normalize($value);
        foreach ($this->parroquiaCache as $cacheKey => $id) {
            if (! str_starts_with($cacheKey, $municipioId.':')) {
                continue;
            }
            $name = substr($cacheKey, strlen((string) $municipioId) + 1);
            if (str_contains($name, $normalized) || str_contains($normalized, $name)) {
                return $id;
            }
        }

        return Parroquia::query()
            ->where('municipio_id', $municipioId)
            ->where('is_active', true)
            ->where('name', 'like', '%'.trim($value).'%')
            ->value('id');
    }

    private function fuzzyCatalogMatch(string $type, string $normalized): ?int
    {
        foreach ($this->catalogCache as $key => $id) {
            if (! str_starts_with($key, $type.':')) {
                continue;
            }
            $name = substr($key, strlen($type) + 1);
            if (str_contains($name, $normalized) || str_contains($normalized, $name)) {
                return $id;
            }
        }

        return null;
    }

    private function catalogKey(string $type, string $value): string
    {
        return $type.':'.$this->normalize($value);
    }

    private function geoKey(int $parentId, string $value): string
    {
        return $parentId.':'.$this->normalize($value);
    }

    private function normalize(string $value): string
    {
        return Str::ascii(Str::lower(trim(preg_replace('/\s+/', ' ', $value) ?? '')));
    }
}
