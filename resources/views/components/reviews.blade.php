{{--
    <x-atrium-reviews::reviews :for="$product" :name="$product->name" :url="url()->current()" />
    The reviews of a record, or of the site without "for".
--}}
@props(['for' => null, 'name' => null, 'url' => null])
@include('atrium-reviews::block', ['record' => $for, 'name' => $name, 'url' => $url ?? url()->current()])
