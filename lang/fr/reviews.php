<?php

declare(strict_types=1);

return [
    'module' => 'Avis',
    'site' => 'Le site',
    'missing' => ':type #:id (supprimé)',
    'status' => [
        'new' => 'Nouveau',
        'published' => 'Publié',
        'rejected' => 'Refusé',
    ],
    'fields' => [
        'status' => 'Statut',
        'rating' => 'Note',
        'author_name' => 'Auteur',
        'posted_at' => 'Date',
        'text' => 'Texte',
        'subject_type' => 'À propos d’un élément de',
        'subject_id' => 'ID de l’élément',
        'subject' => 'Sujet',
    ],
    'title' => 'Avis',
    'description' => 'Avis sur :site',
    'average' => ':rating sur 5',
    'count' => '{1} :count avis|[2,*] :count avis',
    'rated' => ':rating sur 5',
    'empty' => 'Pas encore d’avis. Soyez le premier.',
    'form' => [
        'title' => 'Laisser un avis',
        'name' => 'Votre nom',
        'rating' => 'Note',
        'text' => 'Votre avis',
        'submit' => 'Envoyer',
        'sent' => 'Merci ! Votre avis apparaîtra après modération.',
        'moderation' => 'Les avis apparaissent sur le site après vérification par un modérateur.',
        'too_many' => 'Trop d’avis depuis votre adresse. Réessayez dans :minutes min.',
        'unknown' => 'Cette page n’accepte plus d’avis.',
        'trap' => 'Laissez ce champ vide',
    ],
];
