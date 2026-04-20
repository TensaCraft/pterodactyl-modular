<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('core_modules', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('version');
            $table->text('description')->nullable();
            $table->unsignedInteger('priority')->default(100);
            $table->string('state', 32);
            $table->boolean('is_protected')->default(false);
            $table->boolean('has_frontend')->default(false);
            $table->string('panel_constraint')->default('*');
            $table->string('core_api_constraint')->default('*');
            $table->string('manifest_path');
            $table->timestamps();
        });

        Schema::create('core_module_operations', function (Blueprint $table) {
            $table->id();
            $table->string('module_slug');
            $table->string('operation', 64);
            $table->string('status', 32);
            $table->json('payload')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
            $table->index(['module_slug', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('core_module_operations');
        Schema::dropIfExists('core_modules');
    }
};
