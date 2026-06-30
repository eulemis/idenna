<?php

namespace Tests\Feature;

use App\Models\Operativo;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ImportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_import_preview_returns_headers(): void
    {
        $user = User::query()->where('email', 'admin@idenna.gob.ve')->first();
        Sanctum::actingAs($user);

        $csv = "Nombres,Apellidos,Edad\nJuan,Pérez,8\n";
        $file = UploadedFile::fake()->createWithContent('test.csv', $csv);

        $response = $this->postJson('/api/v1/imports/preview', ['file' => $file]);

        $response->assertOk()
            ->assertJsonPath('data.headers.0', 'Nombres');
    }

    public function test_dashboard_stats_returns_kpis(): void
    {
        $user = User::query()->where('email', 'admin@idenna.gob.ve')->first();
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/dashboard/stats');

        $response->assertOk()
            ->assertJsonStructure(['data' => ['kpis' => ['total', 'today']]]);
    }
}
