{{--
    This file is part of Atrium (https://github.com/AlveBy/atrium).
    Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
--}}
{{--
    <x-atrium-reviews::reviews :for="$product" :name="$product->name" :url="url()->current()" />
    The reviews of a record, or of the site without "for".
--}}
@props(['for' => null, 'name' => null, 'url' => null])
@include('atrium-reviews::block', ['record' => $for, 'name' => $name, 'url' => $url ?? url()->current()])
