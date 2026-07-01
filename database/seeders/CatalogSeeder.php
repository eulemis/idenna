<?php

namespace Database\Seeders;

use App\Enums\CatalogType;
use App\Models\Catalog;
use Illuminate\Database\Seeder;

class CatalogSeeder extends Seeder
{
    public function run(): void
    {
        $catalogs = [
            CatalogType::Genero->value => [
                ['code' => 'M', 'name' => 'Masculino'],
                ['code' => 'F', 'name' => 'Femenino'],
                ['code' => 'NB', 'name' => 'No binario'],
                ['code' => 'ND', 'name' => 'No declarado'],
            ],
            CatalogType::ColorPiel->value => [
                ['code' => 'CLARA', 'name' => 'Clara'],
                ['code' => 'MEDIA', 'name' => 'Media'],
                ['code' => 'MORENA', 'name' => 'Morena'],
                ['code' => 'NEGRA', 'name' => 'Negra'],
            ],
            CatalogType::ColorOjos->value => [
                ['code' => 'CAFE', 'name' => 'Café'],
                ['code' => 'NEGRO', 'name' => 'Negro'],
                ['code' => 'AZUL', 'name' => 'Azul'],
                ['code' => 'VERDE', 'name' => 'Verde'],
                ['code' => 'MIEL', 'name' => 'Miel'],
            ],
            CatalogType::ColorCabello->value => [
                ['code' => 'NEGRO', 'name' => 'Negro'],
                ['code' => 'CASTANO', 'name' => 'Castaño'],
                ['code' => 'RUBIO', 'name' => 'Rubio'],
                ['code' => 'ROJO', 'name' => 'Rojo'],
                ['code' => 'CANA', 'name' => 'Canoso'],
            ],
            CatalogType::TipoDiscapacidad->value => [
                ['code' => 'FISICA', 'name' => 'Física'],
                ['code' => 'VISUAL', 'name' => 'Visual'],
                ['code' => 'AUDITIVA', 'name' => 'Auditiva'],
                ['code' => 'INTELECTUAL', 'name' => 'Intelectual'],
                ['code' => 'PSICOSOCIAL', 'name' => 'Psicosocial'],
                ['code' => 'MULTIPLE', 'name' => 'Múltiple'],
            ],
            CatalogType::Necesidad->value => [
                ['code' => 'ALIMENTACION', 'name' => 'Alimentación'],
                ['code' => 'REFUGIO', 'name' => 'Refugio'],
                ['code' => 'SALUD', 'name' => 'Atención médica'],
                ['code' => 'PSICOLOGICA', 'name' => 'Atención psicológica'],
                ['code' => 'REENCUENTRO', 'name' => 'Reencuentro familiar'],
                ['code' => 'DOCUMENTOS', 'name' => 'Documentación'],
            ],
            CatalogType::Parentesco->value => [
                ['code' => 'MADRE', 'name' => 'Madre'],
                ['code' => 'PADRE', 'name' => 'Padre'],
                ['code' => 'TUTOR', 'name' => 'Tutor legal'],
                ['code' => 'FAMILIAR', 'name' => 'Familiar'],
                ['code' => 'RESPONSABLE', 'name' => 'Responsable adulto'],
                ['code' => 'SOLO', 'name' => 'Sin acompañante'],
            ],
            CatalogType::OrganoActuante->value => [
                ['code' => 'CPNNA', 'name' => 'CPNNA'],
                ['code' => 'CMDNNA', 'name' => 'CMDNNA'],
                ['code' => 'TRIBUNALES', 'name' => 'Tribunales de Protección'],
                ['code' => 'IDENNA', 'name' => 'IDENNA'],
                ['code' => 'CONAPDIS', 'name' => 'CONAPDIS'],
                ['code' => 'OTRO', 'name' => 'Otro'],
            ],
            CatalogType::TipoMedida->value => [
                ['code' => 'ABRIGO', 'name' => 'Abrigo'],
                ['code' => 'INTERNACION', 'name' => 'Internación en centro hospitalario'],
                ['code' => 'CRIANZA', 'name' => 'Responsabilidad de crianza'],
                ['code' => 'PROTECCION', 'name' => 'Medida de protección'],
                ['code' => 'ACOGIDA', 'name' => 'Acogida familiar'],
                ['code' => 'OTRO', 'name' => 'Otro'],
            ],
            CatalogType::Talla->value => [
                ['code' => 'XS', 'name' => 'XS'],
                ['code' => 'S', 'name' => 'S'],
                ['code' => 'M', 'name' => 'M'],
                ['code' => 'L', 'name' => 'L'],
                ['code' => 'XL', 'name' => 'XL'],
            ],
            CatalogType::TipoRefugio->value => [
                ['code' => 'TEMPORAL', 'name' => 'Refugio temporal'],
                ['code' => 'PERMANENTE', 'name' => 'Refugio permanente'],
                ['code' => 'ALBERGUE', 'name' => 'Albergue'],
            ],
            CatalogType::LugarNna->value => [
                ['code' => 'HOSPITAL', 'name' => 'Hospital'],
                ['code' => 'REFUGIO', 'name' => 'Refugio'],
                ['code' => 'CAMPAMENTO', 'name' => 'Campamento'],
                ['code' => 'PARQUE', 'name' => 'Parque'],
                ['code' => 'PLAZA', 'name' => 'Plaza'],
                ['code' => 'CALLE', 'name' => 'Calle / vía pública'],
            ],
        ];

        foreach ($catalogs as $type => $items) {
            foreach ($items as $order => $item) {
                Catalog::withTrashed()->updateOrCreate(
                    ['type' => $type, 'code' => $item['code']],
                    [
                        'name' => $item['name'],
                        'sort_order' => $order + 1,
                        'is_active' => true,
                        'deleted_at' => null,
                    ],
                );
            }
        }
    }
}
