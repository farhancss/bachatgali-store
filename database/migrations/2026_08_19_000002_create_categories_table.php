<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Adjacency list plus a materialised `path` of ancestor ids.
     *
     * The path makes "every product under Electronics, at any depth" a single
     * indexed LIKE 'path%' rather than a recursive CTE — which keeps the
     * listing query portable to SQLite in tests, and keeps the depth of the
     * tree off the query plan.
     */
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('path')->default('');
            $table->unsignedTinyInteger('depth')->default(0);
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();

            $table->index('path');
            $table->index(['parent_id', 'position']);
            $table->index(['is_active', 'depth']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('categories');
    }
};
