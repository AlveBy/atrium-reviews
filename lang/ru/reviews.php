<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

return [
    'module' => 'Отзывы',
    'site' => 'Сайт',
    'missing' => ':type #:id (удалено)',
    'status' => [
        'new' => 'Новый',
        'published' => 'Опубликован',
        'rejected' => 'Отклонён',
    ],
    'fields' => [
        'status' => 'Статус',
        'rating' => 'Оценка',
        'author_name' => 'Автор',
        'posted_at' => 'Дата',
        'text' => 'Текст',
        'subject_type' => 'Отзыв о записи модуля',
        'subject_id' => 'ID записи',
        'subject' => 'О чём',
    ],
    'title' => 'Отзывы',
    'description' => 'Отзывы о :site',
    'average' => ':rating из 5',
    'count' => ':count отзыв|:count отзыва|:count отзывов',
    'rated' => ':rating из 5',
    'empty' => 'Отзывов пока нет. Будьте первым.',
    'form' => [
        'title' => 'Оставить отзыв',
        'name' => 'Ваше имя',
        'rating' => 'Оценка',
        'text' => 'Ваш отзыв',
        'submit' => 'Отправить',
        'sent' => 'Спасибо! Отзыв появится после проверки модератором.',
        'moderation' => 'Отзывы появляются на сайте после проверки модератором.',
        'too_many' => 'Слишком много отзывов с вашего адреса. Попробуйте через :minutes мин.',
        'unknown' => 'Эта страница больше не принимает отзывы.',
        'trap' => 'Оставьте это поле пустым',
    ],
];
