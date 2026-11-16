<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Reviews;

/**
 * Where a review is on its way to the site: a new one waits for a
 * moderator, only a published one is shown.
 */
enum ReviewStatus: string
{
    case New = 'new';
    case Published = 'published';
    case Rejected = 'rejected';

    public function label(): string
    {
        return __('atrium-reviews::reviews.status.'.$this->value);
    }
}
