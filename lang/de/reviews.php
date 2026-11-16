<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

return [
    'module' => 'Bewertungen',
    'site' => 'Die Website',
    'missing' => ':type #:id (entfernt)',
    'status' => [
        'new' => 'Neu',
        'published' => 'Veröffentlicht',
        'rejected' => 'Abgelehnt',
    ],
    'fields' => [
        'status' => 'Status',
        'rating' => 'Bewertung',
        'author_name' => 'Autor',
        'posted_at' => 'Datum',
        'text' => 'Text',
        'subject_type' => 'Über einen Eintrag von',
        'subject_id' => 'Eintrags-ID',
        'subject' => 'Worüber',
    ],
    'title' => 'Bewertungen',
    'description' => 'Bewertungen von :site',
    'average' => ':rating von 5',
    'count' => '{1} :count Bewertung|[2,*] :count Bewertungen',
    'rated' => ':rating von 5',
    'empty' => 'Noch keine Bewertungen. Seien Sie der Erste.',
    'form' => [
        'title' => 'Bewertung schreiben',
        'name' => 'Ihr Name',
        'rating' => 'Bewertung',
        'text' => 'Ihre Bewertung',
        'submit' => 'Senden',
        'sent' => 'Danke! Ihre Bewertung erscheint nach der Prüfung.',
        'moderation' => 'Bewertungen erscheinen nach der Prüfung durch einen Moderator.',
        'too_many' => 'Zu viele Bewertungen von Ihrer Adresse. Versuchen Sie es in :minutes Min. erneut.',
        'unknown' => 'Diese Seite nimmt keine Bewertungen mehr an.',
        'trap' => 'Dieses Feld leer lassen',
    ],
];
