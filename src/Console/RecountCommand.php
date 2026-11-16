<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Reviews\Console;

use Atrium\Reviews\ReviewCounters;
use Illuminate\Console\Command;

/**
 * Counts the reviews of every record again: after an import or after the
 * reviews were changed in the database directly.
 */
final class RecountCommand extends Command
{
    protected $signature = 'atrium:reviews:recount';

    protected $description = 'Count the published reviews of every record again';

    public function handle(ReviewCounters $counters): int
    {
        $this->components->info('Counted the reviews of '.$counters->rebuild().' records.');

        return self::SUCCESS;
    }
}
