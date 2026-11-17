<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Reviews\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;

/** Not a module of the panel, yet it has a method named like the panel's title. */
final class Titled extends Model
{
    protected $table = 'review_articles';

    protected $guarded = [];

    public function getAdminTitle(): string
    {
        return 'Not the title of a module';
    }
}
