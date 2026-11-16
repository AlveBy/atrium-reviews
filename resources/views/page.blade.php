{{--
    This file is part of Atrium (https://github.com/AlveBy/atrium).
    Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
--}}
{{-- The reviews of the site: in the public theme's frame when it is installed. --}}
@extends(view()->exists('atrium-theme::layout') ? 'atrium-theme::layout' : 'atrium-reviews::layout', [
    'metaTitle' => __('atrium-reviews::reviews.title'),
    'metaDescription' => __('atrium-reviews::reviews.description', ['site' => $siteTitle]),
])

@section('content')
    <h1>{{ __('atrium-reviews::reviews.title') }}</h1>

    @include('atrium-reviews::block', ['record' => null, 'name' => $siteTitle, 'url' => $canonical, 'items' => $items])
@endsection
