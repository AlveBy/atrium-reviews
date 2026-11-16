<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Two modules of a made-up site: products take reviews, articles do not.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['review_products', 'review_articles'] as $name) {
            Schema::create($name, function (Blueprint $table): void {
                $table->id();
                $table->string('name');
                $table->string('code');
                $table->boolean('active')->default(true);
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('review_articles');
        Schema::dropIfExists('review_products');
    }
};
