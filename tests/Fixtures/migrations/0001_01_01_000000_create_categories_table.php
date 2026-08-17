<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->unsignedInteger('position')->default(0);
            $table->string('name');
            // ⚠️ TWO separate booleans, deliberately.
            //
            // `is_active` answers "may this node receive children?" — the host's
            // isValidTreeTarget(). `is_visible` answers "may this actor see it?" —
            // the host's privacy scope. They are different questions about
            // different things (data-model.md), and a fixture with one column
            // could not tell a package that conflated them.
            $table->boolean('is_active')->default(true);
            $table->boolean('is_visible')->default(true);

            $table->index(['parent_id', 'position']);
        });
    }
};
