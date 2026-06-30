<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('import_batches', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('operativo_id')->constrained('operativos')->restrictOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->string('filename');
            $table->string('status', 20)->default('pending');
            $table->unsignedInteger('total_rows')->default(0);
            $table->unsignedInteger('success_rows')->default(0);
            $table->unsignedInteger('failed_rows')->default(0);
            $table->json('column_mapping')->nullable();
            $table->json('errors')->nullable();
            $table->json('summary')->nullable();
            $table->timestamps();

            $table->index(['operativo_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('import_batches');
    }
};
