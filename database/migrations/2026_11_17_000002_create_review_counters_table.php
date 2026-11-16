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
        // The published reviews of each record, counted once when a review
        // changes instead of on every page. The key has no NULLs, so the
        // unique index holds for the site's own reviews too: 0 is "no site",
        // '' and 0 are "the site as a whole".
        Schema::create('review_counters', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('site_id')->default(0);
            $table->string('reviewable_type')->default('');
            $table->unsignedBigInteger('reviewable_id')->default(0);
            $table->unsignedInteger('count')->default(0);
            $table->unsignedInteger('rating_sum')->default(0);

            foreach (range(1, 5) as $rating) {
                $table->unsignedInteger('rating_'.$rating)->default(0);
            }

            $table->timestamps();

            $table->unique(['reviewable_type', 'reviewable_id', 'site_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('review_counters');
    }
};
