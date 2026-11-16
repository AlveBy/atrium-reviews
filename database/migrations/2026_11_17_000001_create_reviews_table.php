<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reviews', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('site_id')->nullable()->index();
            // What the review is about; both empty: the site as a whole. No
            // foreign key: the reviewed module may be removed, its reviews stay.
            $table->string('reviewable_type')->nullable();
            $table->unsignedBigInteger('reviewable_id')->nullable();
            $table->string('author_name');
            $table->unsignedTinyInteger('rating');
            $table->text('text');
            $table->string('status', 16)->default('new')->index();
            $table->timestamp('posted_at')->nullable();
            $table->timestamps();

            $table->index(['reviewable_type', 'reviewable_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reviews');
    }
};
