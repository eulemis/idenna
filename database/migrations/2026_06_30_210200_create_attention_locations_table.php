<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attention_locations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('operativo_id')->nullable()->constrained('operativos')->nullOnDelete();
            $table->string('type', 30);
            $table->string('name');
            $table->foreignId('estado_id')->nullable()->constrained('estados')->nullOnDelete();
            $table->foreignId('municipio_id')->nullable()->constrained('municipios')->nullOnDelete();
            $table->foreignId('parroquia_id')->nullable()->constrained('parroquias')->nullOnDelete();
            $table->string('address')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->boolean('is_active')->default(true);
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['type', 'is_active']);
            $table->index(['operativo_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attention_locations');
    }
};
