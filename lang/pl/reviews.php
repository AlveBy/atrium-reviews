<?php

declare(strict_types=1);

return [
    'module' => 'Opinie',
    'site' => 'Strona',
    'missing' => ':type #:id (usunięto)',
    'status' => [
        'new' => 'Nowa',
        'published' => 'Opublikowana',
        'rejected' => 'Odrzucona',
    ],
    'fields' => [
        'status' => 'Status',
        'rating' => 'Ocena',
        'author_name' => 'Autor',
        'posted_at' => 'Data',
        'text' => 'Treść',
        'subject_type' => 'O wpisie modułu',
        'subject_id' => 'ID wpisu',
        'subject' => 'Czego dotyczy',
    ],
    'title' => 'Opinie',
    'description' => 'Opinie o :site',
    'average' => ':rating na 5',
    'count' => ':count opinia|:count opinie|:count opinii',
    'rated' => ':rating na 5',
    'empty' => 'Nie ma jeszcze opinii. Bądź pierwszy.',
    'form' => [
        'title' => 'Dodaj opinię',
        'name' => 'Twoje imię',
        'rating' => 'Ocena',
        'text' => 'Twoja opinia',
        'submit' => 'Wyślij',
        'sent' => 'Dziękujemy! Opinia pojawi się po moderacji.',
        'moderation' => 'Opinie pojawiają się na stronie po sprawdzeniu przez moderatora.',
        'too_many' => 'Zbyt wiele opinii z Twojego adresu. Spróbuj za :minutes min.',
        'unknown' => 'Ta strona nie przyjmuje już opinii.',
        'trap' => 'Pozostaw to pole puste',
    ],
];
