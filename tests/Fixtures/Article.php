<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Reviews\Tests\Fixtures;

use Atrium\Admin\Fields\TextField;
use Illuminate\Database\Eloquent\Model;

/** A module that does not take reviews. */
class Article extends Model
{
    protected $table = 'review_articles';

    protected $guarded = [];

    public static function getAdminLabel(): string
    {
        return 'Articles';
    }

    public static function getAdministrationFields(): array
    {
        return [TextField::make('name')->label('Name')];
    }
}
