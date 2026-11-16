{{-- A plain frame for the reviews page of a site without the public theme. --}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $metaTitle }}</title>
    @if (! empty($metaDescription))
    <meta name="description" content="{{ $metaDescription }}">
    @endif
    @if (! empty($canonical))
    <link rel="canonical" href="{{ $canonical }}">
    @endif
    <x-atrium::hreflang :urls="$alternates ?? []" />
    <x-atrium::head />
    <style>body { margin: 0 auto; max-width: 48rem; padding: 2rem 1.25rem; font: 1rem/1.6 system-ui, sans-serif; }</style>
</head>
<body>
    <x-atrium::body-start />
    <main id="content">
        @yield('content')
    </main>
    <x-atrium::body-end />
</body>
</html>
