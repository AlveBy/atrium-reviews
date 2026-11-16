<?php

declare(strict_types=1);

return [
    'module' => 'Avaliações',
    'site' => 'O site',
    'missing' => ':type #:id (removido)',
    'status' => [
        'new' => 'Nova',
        'published' => 'Publicada',
        'rejected' => 'Rejeitada',
    ],
    'fields' => [
        'status' => 'Status',
        'rating' => 'Nota',
        'author_name' => 'Autor',
        'posted_at' => 'Data',
        'text' => 'Texto',
        'subject_type' => 'Sobre um registro de',
        'subject_id' => 'ID do registro',
        'subject' => 'Sobre',
    ],
    'title' => 'Avaliações',
    'description' => 'Avaliações de :site',
    'average' => ':rating de 5',
    'count' => '{1} :count avaliação|[2,*] :count avaliações',
    'rated' => ':rating de 5',
    'empty' => 'Ainda não há avaliações. Seja o primeiro.',
    'form' => [
        'title' => 'Deixe uma avaliação',
        'name' => 'Seu nome',
        'rating' => 'Nota',
        'text' => 'Sua avaliação',
        'submit' => 'Enviar',
        'sent' => 'Obrigado! Sua avaliação aparecerá após a moderação.',
        'moderation' => 'As avaliações aparecem no site depois que um moderador as verifica.',
        'too_many' => 'Muitas avaliações do seu endereço. Tente novamente em :minutes min.',
        'unknown' => 'Esta página não aceita mais avaliações.',
        'trap' => 'Deixe este campo vazio',
    ],
];
