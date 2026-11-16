{{--
    This file is part of Atrium (https://github.com/AlveBy/atrium).
    Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
--}}
{{-- Under a record page of the public theme: only for the models that take reviews. --}}
@if (app(\Atrium\Reviews\Reviews::class)->accepts($record))
    @include('atrium-reviews::block', ['record' => $record, 'name' => $page->title(), 'url' => $page->url()])
@endif
