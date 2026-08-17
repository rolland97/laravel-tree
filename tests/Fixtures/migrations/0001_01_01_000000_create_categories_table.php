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
            // ⚠️ A SECOND identifier the package knows nothing about, and the
            // reason it exists is PA-5. `matchesSearch()` searches the configured
            // tie-breaker — here `name` — and the first real consumer searches
            // name OR short_code, because its own placeholder copy promises it
            // (the consumer's adoption, research F6). Without a second searchable column
            // no fixture could tell a package that ignored the host's override
            // from one that honoured it. Nullable, so every existing fixture row
            // is untouched.
            $table->string('short_code')->nullable();
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
