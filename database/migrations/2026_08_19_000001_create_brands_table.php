<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('brands', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();

            $table->index(['is_active', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('brands');
    }
};
