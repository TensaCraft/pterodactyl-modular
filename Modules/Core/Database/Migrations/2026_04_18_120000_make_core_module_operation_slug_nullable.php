<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('core_module_operations', function (Blueprint $table) {
            $table->string('module_slug')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('core_module_operations', function (Blueprint $table) {
            $table->string('module_slug')->nullable(false)->change();
        });
    }
};
