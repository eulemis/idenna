<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('nna_registrations', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->uuid('local_uuid')->unique();
            $table->foreignId('operativo_id')->constrained('operativos')->restrictOnDelete();
            $table->string('registration_code', 30)->nullable();

            $table->string('first_name');
            $table->string('last_name');
            $table->date('birth_date')->nullable();
            $table->unsignedTinyInteger('age_years')->nullable();
            $table->foreignId('gender_id')->nullable()->constrained('catalogs')->nullOnDelete();

            $table->foreignId('skin_color_id')->nullable()->constrained('catalogs')->nullOnDelete();
            $table->foreignId('eye_color_id')->nullable()->constrained('catalogs')->nullOnDelete();
            $table->foreignId('hair_color_id')->nullable()->constrained('catalogs')->nullOnDelete();
            $table->foreignId('size_id')->nullable()->constrained('catalogs')->nullOnDelete();

            $table->foreignId('estado_id')->nullable()->constrained('estados')->nullOnDelete();
            $table->foreignId('municipio_id')->nullable()->constrained('municipios')->nullOnDelete();
            $table->foreignId('parroquia_id')->nullable()->constrained('parroquias')->nullOnDelete();
            $table->foreignId('attention_location_id')->nullable()->constrained('attention_locations')->nullOnDelete();
            $table->foreignId('lugar_nna_id')->nullable()->constrained('catalogs')->nullOnDelete();

            $table->text('notes')->nullable();
            $table->string('status', 20)->default('draft');

            $table->foreignId('registered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('registered_at')->nullable();
            $table->string('device_name')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();

            $table->timestamp('synced_at')->nullable();
            $table->unsignedInteger('server_version')->default(1);
            $table->json('metadata')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['operativo_id', 'status']);
            $table->index(['estado_id', 'municipio_id']);
            $table->index('registered_at');
        });

        Schema::create('nna_acompanantes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('nna_registration_id')->constrained('nna_registrations')->cascadeOnDelete();
            $table->string('first_name');
            $table->string('last_name')->nullable();
            $table->string('document_id', 30)->nullable();
            $table->foreignId('relationship_id')->nullable()->constrained('catalogs')->nullOnDelete();
            $table->string('phone', 20)->nullable();
            $table->boolean('is_primary_contact')->default(false);
            $table->timestamps();
        });

        Schema::create('nna_catalog', function (Blueprint $table) {
            $table->id();
            $table->foreignId('nna_registration_id')->constrained('nna_registrations')->cascadeOnDelete();
            $table->foreignId('catalog_id')->constrained('catalogs')->cascadeOnDelete();
            $table->string('catalog_type', 50);
            $table->timestamps();

            $table->unique(['nna_registration_id', 'catalog_id']);
            $table->index(['nna_registration_id', 'catalog_type']);
        });

        Schema::create('nna_photos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('nna_registration_id')->constrained('nna_registrations')->cascadeOnDelete();
            $table->string('disk', 20)->default('local');
            $table->string('path');
            $table->string('thumbnail_path')->nullable();
            $table->string('mime_type', 50)->nullable();
            $table->unsignedInteger('size_bytes')->nullable();
            $table->boolean('is_primary')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nna_photos');
        Schema::dropIfExists('nna_catalog');
        Schema::dropIfExists('nna_acompanantes');
        Schema::dropIfExists('nna_registrations');
    }
};
