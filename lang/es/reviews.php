<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

return [
    'module' => 'Reseñas',
    'site' => 'El sitio',
    'missing' => ':type #:id (eliminado)',
    'status' => [
        'new' => 'Nueva',
        'published' => 'Publicada',
        'rejected' => 'Rechazada',
    ],
    'fields' => [
        'status' => 'Estado',
        'rating' => 'Valoración',
        'author_name' => 'Autor',
        'posted_at' => 'Fecha',
        'text' => 'Texto',
        'subject_type' => 'Sobre un registro de',
        'subject_id' => 'ID del registro',
        'subject' => 'Sobre',
    ],
    'title' => 'Reseñas',
    'description' => 'Reseñas de :site',
    'average' => ':rating de 5',
    'count' => '{1} :count reseña|[2,*] :count reseñas',
    'rated' => ':rating de 5',
    'empty' => 'Aún no hay reseñas. Sé el primero.',
    'form' => [
        'title' => 'Deja una reseña',
        'name' => 'Tu nombre',
        'rating' => 'Valoración',
        'text' => 'Tu reseña',
        'submit' => 'Enviar',
        'sent' => '¡Gracias! Tu reseña aparecerá tras la moderación.',
        'moderation' => 'Las reseñas aparecen en el sitio después de que un moderador las revise.',
        'too_many' => 'Demasiadas reseñas desde tu dirección. Inténtalo de nuevo en :minutes min.',
        'unknown' => 'Esta página ya no acepta reseñas.',
        'trap' => 'Deja este campo vacío',
    ],
];
