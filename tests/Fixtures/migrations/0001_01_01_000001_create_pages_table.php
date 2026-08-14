<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ⚠️ Different column names from `categories`, and NO `name` column — see the
 * class comment on Rolland\Tree\Tests\Fixtures\Page.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('section_id')->nullable()->constrained('pages')->nullOnDelete();
            $table->unsignedInteger('sort_order')->default(0);
            $table->string('title');
            $table->boolean('published')->default(true);

            $table->index(['section_id', 'sort_order']);
        });
    }
};
