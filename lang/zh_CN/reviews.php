<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

return [
    'module' => '评价',
    'site' => '网站',
    'missing' => ':type #:id（已删除）',
    'status' => [
        'new' => '新',
        'published' => '已发布',
        'rejected' => '已拒绝',
    ],
    'fields' => [
        'status' => '状态',
        'rating' => '评分',
        'author_name' => '作者',
        'posted_at' => '日期',
        'text' => '内容',
        'subject_type' => '关于以下模块的记录',
        'subject_id' => '记录 ID',
        'subject' => '关于',
    ],
    'title' => '评价',
    'description' => ':site 的评价',
    'average' => ':rating / 5',
    'count' => ':count 条评价',
    'rated' => ':rating / 5',
    'empty' => '暂无评价，来写第一条吧。',
    'form' => [
        'title' => '写评价',
        'name' => '您的姓名',
        'rating' => '评分',
        'text' => '您的评价',
        'submit' => '提交',
        'sent' => '谢谢！评价审核后显示。',
        'moderation' => '评价经管理员审核后在网站上显示。',
        'too_many' => '您的地址提交的评价过多，请 :minutes 分钟后再试。',
        'unknown' => '此页面不再接受评价。',
        'trap' => '请将此字段留空',
    ],
];
