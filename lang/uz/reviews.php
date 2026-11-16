<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

return [
    'module' => 'Sharhlar',
    'site' => 'Sayt',
    'missing' => ':type #:id (oʻchirilgan)',
    'status' => [
        'new' => 'Yangi',
        'published' => 'Eʼlon qilingan',
        'rejected' => 'Rad etilgan',
    ],
    'fields' => [
        'status' => 'Holat',
        'rating' => 'Baho',
        'author_name' => 'Muallif',
        'posted_at' => 'Sana',
        'text' => 'Matn',
        'subject_type' => 'Modul yozuvi haqida',
        'subject_id' => 'Yozuv ID',
        'subject' => 'Nima haqida',
    ],
    'title' => 'Sharhlar',
    'description' => ':site haqida sharhlar',
    'average' => '5 dan :rating',
    'count' => ':count ta sharh',
    'rated' => '5 dan :rating',
    'empty' => 'Hozircha sharhlar yoʻq. Birinchi boʻling.',
    'form' => [
        'title' => 'Sharh qoldirish',
        'name' => 'Ismingiz',
        'rating' => 'Baho',
        'text' => 'Sharhingiz',
        'submit' => 'Yuborish',
        'sent' => 'Rahmat! Sharh moderator tekshirganidan keyin chiqadi.',
        'moderation' => 'Sharhlar saytda moderator tekshirganidan keyin chiqadi.',
        'too_many' => 'Manzilingizdan juda koʻp sharh. :minutes daqiqadan keyin urinib koʻring.',
        'unknown' => 'Bu sahifa endi sharh qabul qilmaydi.',
        'trap' => 'Bu maydonni boʻsh qoldiring',
    ],
];
