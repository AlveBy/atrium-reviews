<?php

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
