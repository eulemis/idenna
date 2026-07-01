<?php

namespace App\Console\Commands;

use App\Models\Operativo;
use App\Services\Import\ImportRegistrarService;
use Illuminate\Console\Command;

class ImportRegistradoresCommand extends Command
{
    protected $signature = 'nna:import-registradores
                            {file : Ruta al Excel/CSV de Google Forms}
                            {--operativo= : ID del operativo (por defecto TER-2026-VE-001)}
                            {--link-only : Solo vincular registros NNA existentes con usuarios por cédula}
                            {--password= : Contraseña inicial (default: config idenna / Registrador123!)}
                            {--reset-password : Actualizar contraseña de usuarios ya existentes}
                            {--dry-run : Simular sin escribir en base de datos}';

    protected $description = 'Extrae registradores del Excel, crea usuarios y vincula registros NNA por cédula';

    public function handle(ImportRegistrarService $service): int
    {
        $operativo = $this->resolveOperativo();
        if (! $operativo) {
            $this->error('Operativo no encontrado.');

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');
        if ($dryRun) {
            $this->warn('Modo simulación — no se escribirá en la base de datos.');
        }

        if (! $this->option('link-only')) {
            $path = $this->argument('file');
            if (! is_readable($path)) {
                $this->error("No se puede leer: {$path}");

                return self::FAILURE;
            }

            $defaultPassword = $this->option('password')
                ?: config('idenna.import_registrar_default_password', 'Registrador123!');

            $this->line("Contraseña asignada a usuarios nuevos: {$defaultPassword}");
            if ($this->option('reset-password')) {
                $this->warn('Se actualizará la contraseña de usuarios existentes que coincidan por cédula.');
            }

            $registrars = $service->extractFromPath($path);
            $this->info("Registradores únicos encontrados: {$registrars->count()}");

            $this->table(
                ['Cédula', 'Nombre', 'Filas'],
                $registrars->take(15)->map(fn ($r) => [$r['document_id'], $r['name'], $r['rows']])->all(),
            );

            if ($registrars->count() > 15) {
                $this->line('… y '.($registrars->count() - 15).' más.');
            }

            $sync = $service->syncUsers(
                $registrars,
                $operativo->id,
                $dryRun,
                $defaultPassword,
                (bool) $this->option('reset-password'),
            );
            $this->info("Usuarios — creados: {$sync['created']}, actualizados: {$sync['updated']}");
            if (($sync['passwordReset'] ?? 0) > 0) {
                $this->info("Contraseñas reseteadas: {$sync['passwordReset']}");
            }
        }

        $link = $service->linkRegistrations($operativo->id, $dryRun);
        $this->info("Vinculación — enlazados: {$link['linked']}, ya correctos: {$link['already_linked']}, sin match: {$link['unmatched']}");

        return self::SUCCESS;
    }

    private function resolveOperativo(): ?Operativo
    {
        $id = $this->option('operativo');
        if ($id) {
            return Operativo::query()->find($id);
        }

        return Operativo::query()->where('code', 'TER-2026-VE-001')->first();
    }
}
