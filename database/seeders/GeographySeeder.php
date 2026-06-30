<?php

namespace Database\Seeders;

use App\Models\Estado;
use App\Models\Municipio;
use App\Models\Parroquia;
use Illuminate\Database\Seeder;

class GeographySeeder extends Seeder
{
    public function run(): void
    {
        $estados = [
            ['code' => 'VE-A', 'name' => 'Distrito Capital'],
            ['code' => 'VE-B', 'name' => 'Anzoátegui'],
            ['code' => 'VE-C', 'name' => 'Apure'],
            ['code' => 'VE-D', 'name' => 'Aragua'],
            ['code' => 'VE-E', 'name' => 'Barinas'],
            ['code' => 'VE-F', 'name' => 'Bolívar'],
            ['code' => 'VE-G', 'name' => 'Carabobo'],
            ['code' => 'VE-H', 'name' => 'Cojedes'],
            ['code' => 'VE-I', 'name' => 'Falcón'],
            ['code' => 'VE-J', 'name' => 'Guárico'],
            ['code' => 'VE-K', 'name' => 'Lara'],
            ['code' => 'VE-L', 'name' => 'Mérida'],
            ['code' => 'VE-M', 'name' => 'Miranda'],
            ['code' => 'VE-N', 'name' => 'Monagas'],
            ['code' => 'VE-O', 'name' => 'Nueva Esparta'],
            ['code' => 'VE-P', 'name' => 'Portuguesa'],
            ['code' => 'VE-R', 'name' => 'Sucre'],
            ['code' => 'VE-S', 'name' => 'Táchira'],
            ['code' => 'VE-T', 'name' => 'Trujillo'],
            ['code' => 'VE-U', 'name' => 'Yaracuy'],
            ['code' => 'VE-V', 'name' => 'Zulia'],
            ['code' => 'VE-W', 'name' => 'La Guaira'],
            ['code' => 'VE-X', 'name' => 'Delta Amacuro'],
            ['code' => 'VE-Y', 'name' => 'Amazonas'],
        ];

        foreach ($estados as $estado) {
            Estado::query()->updateOrCreate(['code' => $estado['code']], $estado);
        }

        $dc = Estado::query()->where('code', 'VE-A')->first();
        if ($dc) {
            $libertador = Municipio::query()->updateOrCreate(
                ['estado_id' => $dc->id, 'code' => '0101'],
                ['name' => 'Libertador']
            );
            foreach (['Catedral', 'Altagracia', 'Candelaria', 'San José', 'El Recreo', 'San Juan'] as $i => $name) {
                Parroquia::query()->updateOrCreate(
                    ['municipio_id' => $libertador->id, 'code' => sprintf('0101%02d', $i + 1)],
                    ['name' => $name]
                );
            }
        }

        $miranda = Estado::query()->where('code', 'VE-M')->first();
        if ($miranda) {
            $municipios = [
                ['code' => '1301', 'name' => 'Acevedo'],
                ['code' => '1302', 'name' => 'Baruta'],
                ['code' => '1303', 'name' => 'Chacao'],
                ['code' => '1304', 'name' => 'El Hatillo'],
                ['code' => '1305', 'name' => 'Sucre'],
            ];
            foreach ($municipios as $mun) {
                Municipio::query()->updateOrCreate(
                    ['estado_id' => $miranda->id, 'code' => $mun['code']],
                    ['name' => $mun['name']]
                );
            }
        }
    }
}
