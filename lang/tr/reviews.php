<?php

declare(strict_types=1);

return [
    'module' => 'Yorumlar',
    'site' => 'Site',
    'missing' => ':type #:id (silindi)',
    'status' => [
        'new' => 'Yeni',
        'published' => 'Yayınlandı',
        'rejected' => 'Reddedildi',
    ],
    'fields' => [
        'status' => 'Durum',
        'rating' => 'Puan',
        'author_name' => 'Yazar',
        'posted_at' => 'Tarih',
        'text' => 'Metin',
        'subject_type' => 'Şu modülün kaydı hakkında',
        'subject_id' => 'Kayıt ID',
        'subject' => 'Konu',
    ],
    'title' => 'Yorumlar',
    'description' => ':site hakkında yorumlar',
    'average' => '5 üzerinden :rating',
    'count' => ':count yorum',
    'rated' => '5 üzerinden :rating',
    'empty' => 'Henüz yorum yok. İlk siz olun.',
    'form' => [
        'title' => 'Yorum yazın',
        'name' => 'Adınız',
        'rating' => 'Puan',
        'text' => 'Yorumunuz',
        'submit' => 'Gönder',
        'sent' => 'Teşekkürler! Yorumunuz moderasyondan sonra görünecek.',
        'moderation' => 'Yorumlar bir moderatör kontrol ettikten sonra sitede görünür.',
        'too_many' => 'Adresinizden çok fazla yorum geldi. :minutes dk sonra tekrar deneyin.',
        'unknown' => 'Bu sayfa artık yorum kabul etmiyor.',
        'trap' => 'Bu alanı boş bırakın',
    ],
];
