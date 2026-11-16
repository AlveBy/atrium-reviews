<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

return [
    'module' => 'レビュー',
    'site' => 'サイト',
    'missing' => ':type #:id（削除済み）',
    'status' => [
        'new' => '新規',
        'published' => '公開',
        'rejected' => '却下',
    ],
    'fields' => [
        'status' => 'ステータス',
        'rating' => '評価',
        'author_name' => '投稿者',
        'posted_at' => '日付',
        'text' => '本文',
        'subject_type' => '次のモジュールのレコードについて',
        'subject_id' => 'レコード ID',
        'subject' => '対象',
    ],
    'title' => 'レビュー',
    'description' => ':site のレビュー',
    'average' => '5 段階中 :rating',
    'count' => ':count 件のレビュー',
    'rated' => '5 段階中 :rating',
    'empty' => 'まだレビューはありません。最初のレビューを書きましょう。',
    'form' => [
        'title' => 'レビューを書く',
        'name' => 'お名前',
        'rating' => '評価',
        'text' => 'レビュー',
        'submit' => '送信',
        'sent' => 'ありがとうございます。レビューは承認後に表示されます。',
        'moderation' => 'レビューはモデレーターの確認後にサイトに表示されます。',
        'too_many' => 'このアドレスからのレビューが多すぎます。:minutes 分後にもう一度お試しください。',
        'unknown' => 'このページはレビューを受け付けていません。',
        'trap' => 'この欄は空のままにしてください',
    ],
];
