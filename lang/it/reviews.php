<?php

declare(strict_types=1);

return [
    'module' => 'Recensioni',
    'site' => 'Il sito',
    'missing' => ':type #:id (rimosso)',
    'status' => [
        'new' => 'Nuova',
        'published' => 'Pubblicata',
        'rejected' => 'Rifiutata',
    ],
    'fields' => [
        'status' => 'Stato',
        'rating' => 'Valutazione',
        'author_name' => 'Autore',
        'posted_at' => 'Data',
        'text' => 'Testo',
        'subject_type' => 'Su un elemento di',
        'subject_id' => 'ID elemento',
        'subject' => 'Riguardo a',
    ],
    'title' => 'Recensioni',
    'description' => 'Recensioni di :site',
    'average' => ':rating su 5',
    'count' => '{1} :count recensione|[2,*] :count recensioni',
    'rated' => ':rating su 5',
    'empty' => 'Ancora nessuna recensione. Sii il primo.',
    'form' => [
        'title' => 'Lascia una recensione',
        'name' => 'Il tuo nome',
        'rating' => 'Valutazione',
        'text' => 'La tua recensione',
        'submit' => 'Invia',
        'sent' => 'Grazie! La recensione apparirà dopo la moderazione.',
        'moderation' => 'Le recensioni appaiono sul sito dopo il controllo di un moderatore.',
        'too_many' => 'Troppe recensioni dal tuo indirizzo. Riprova tra :minutes min.',
        'unknown' => 'Questa pagina non accetta più recensioni.',
        'trap' => 'Lascia vuoto questo campo',
    ],
];
