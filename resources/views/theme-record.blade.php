{{-- Under a record page of the public theme: only for the models that take reviews. --}}
@if (app(\Atrium\Reviews\Reviews::class)->accepts($record))
    @include('atrium-reviews::block', ['record' => $record, 'name' => $page->title(), 'url' => $page->url()])
@endif
